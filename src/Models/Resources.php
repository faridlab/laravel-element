<?php

namespace Element\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Model;
use DB;
use Element\Observers\Observer as Observer;

class Resources extends Model {

  use SoftDeletes;

  /**
   *  TODO:
   * [v] make Model comply withTrashed()
   * [] make Model hidden attribute dynamically changes when querying data (hidden attr for now it is static)
   *
  **/

  protected $guard_name = 'web';
  protected $limit_chars = 50;
  protected $rules = array();
  protected $dates = ['deleted_at'];
  protected $searchable = array();

  //  OBSERVER
  protected static function boot() {
    parent::boot();
    static::observe(Observer::class);
  }

  function setRules($rules) {
    $this->rules = $rules;
  }

  function getRules() {
    return $this->rules;
  }

  function getSearchable() {
    return $this->searchable;
  }

  function validator($request) {
    $validator = Validator::make($request->all(), $this->getRules());
    // NOTE: add some custom validator below
    // $validator->after(function ($validator) use ($request) {
    //   if ($request->get('field_name')) {
    //     if (true) {
    //       $validator->errors()->add('field_name', 'Error goes here');
    //     }
    //   }
    // });
    return $validator;
  }

  public function getStructure() {
    $not_displayed = array_merge(array("id", "created_at", "updated_at", "deleted_at"), $this->getHidden());
    $structures = DB::select('SHOW FIELDS FROM '. $this->getTable());

    $fields = array();
    foreach ($structures as $col) {
      $field = $this->extractColumnSpec($col->Type);
      $field['field'] = $col->Field;
      $field['null'] = $col->Null;
      $field['key'] = $col->Key;
      $field['default'] = $col->Default;
      $field['extra'] = $col->Extra;
      $field['display'] = !in_array($col->Field, $not_displayed);
      $fields[] = $field;
    }
    return $fields;
  }

  public function extractColumnSpec($columnspec)
  {
      $first_bracket_pos = mb_strpos($columnspec, '(');
      if ($first_bracket_pos) {
          $spec_in_brackets = chop(
              mb_substr(
                  $columnspec,
                  $first_bracket_pos + 1,
                  mb_strrpos($columnspec, ')') - $first_bracket_pos - 1
              )
          );
          // convert to lowercase just to be sure
          $type = mb_strtolower(
              chop(mb_substr($columnspec, 0, $first_bracket_pos))
          );
      } else {
          // Split trailing attributes such as unsigned,
          // binary, zerofill and get data type name
          $type_parts = explode(' ', $columnspec);
          $type = mb_strtolower($type_parts[0]);
          $spec_in_brackets = '';
      }

      if ('enum' == $type || 'set' == $type) {
          // Define our working vars
          $enum_set_values = $this->parseEnumSetValues($columnspec, false);
          $printtype = $type
              . '(' .  str_replace("','", "', '", $spec_in_brackets) . ')';
          $binary = false;
          $unsigned = false;
          $zerofill = false;
      } else {
          $enum_set_values = array();

          /* Create printable type name */
          $printtype = mb_strtolower($columnspec);

          // Strip the "BINARY" attribute, except if we find "BINARY(" because
          // this would be a BINARY or VARBINARY column type;
          // by the way, a BLOB should not show the BINARY attribute
          // because this is not accepted in MySQL syntax.
          if (preg_match('@binary@', $printtype)
              && ! preg_match('@binary[\(]@', $printtype)
          ) {
              $printtype = preg_replace('@binary@', '', $printtype);
              $binary = true;
          } else {
              $binary = false;
          }

          $printtype = preg_replace(
              '@zerofill@', '', $printtype, -1, $zerofill_cnt
          );
          $zerofill = ($zerofill_cnt > 0);
          $printtype = preg_replace(
              '@unsigned@', '', $printtype, -1, $unsigned_cnt
          );
          $unsigned = ($unsigned_cnt > 0);
          $printtype = trim($printtype);
      }

      $attribute     = ' ';
      if ($binary) {
          $attribute = 'BINARY';
      }
      if ($unsigned) {
          $attribute = 'UNSIGNED';
      }
      if ($zerofill) {
          $attribute = 'UNSIGNED ZEROFILL';
      }

      $can_contain_collation = false;
      if (! $binary
          && preg_match(
              "@^(char|varchar|text|tinytext|mediumtext|longtext|set|enum)@", $type
          )
      ) {
          $can_contain_collation = true;
      }

      // for the case ENUM('&#8211;','&ldquo;')
      $displayed_type = htmlspecialchars($printtype);
      if (mb_strlen($printtype) > $this->limit_chars) {
        $displayed_type  = '<abbr title="' . htmlspecialchars($printtype) . '">';
        $displayed_type .= htmlspecialchars(
            mb_substr(
                $printtype, 0, $this->limit_chars
            ) . '...'
        );
        $displayed_type .= '</abbr>';
      }

      return array(
        'type' => $type,
        'length' => intval($spec_in_brackets),
        'spec_in_brackets' => $spec_in_brackets,
        'enum_set_values'  => $enum_set_values,
        'print_type' => $printtype,
        'binary' => $binary,
        'unsigned' => $unsigned,
        'zerofill' => $zerofill,
        'attribute' => $attribute,
        'can_contain_collation' => $can_contain_collation,
        'displayed_type' => $displayed_type,
        'value' => null
      );
  }

  public static function parseEnumSetValues($definition, $escapeHtml = true)
  {
      $values_string = htmlentities($definition, ENT_COMPAT, "UTF-8");
      $values = array();
      $in_string = false;
      $buffer = '';

      for ($i = 0, $length = mb_strlen($values_string);
          $i < $length;
          $i++
      ) {
          $curr = mb_substr($values_string, $i, 1);
          $next = ($i == mb_strlen($values_string) - 1)
              ? ''
              : mb_substr($values_string, $i + 1, 1);

          if (! $in_string && $curr == "'") {
              $in_string = true;
          } elseif (($in_string && $curr == "\\") && $next == "\\") {
              $buffer .= "&#92;";
              $i++;
          } elseif (($in_string && $next == "'")
              && ($curr == "'" || $curr == "\\")
          ) {
              $buffer .= "&#39;";
              $i++;
          } elseif ($in_string && $curr == "'") {
              $in_string = false;
              $values[] = $buffer;
              $buffer = '';
          } elseif ($in_string) {
              $buffer .= $curr;
          }

      }

      if (strlen($buffer) > 0) {
          $values[] = $buffer;
      }

      if (! $escapeHtml) {
          foreach ($values as $key => $value) {
              $values[$key] = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
          }
      }

      return $values;
  }

  public function checkTableExists($table_name) {
    $_tables = DB::select('SHOW TABLES');
    $tables = [];
    foreach ($_tables as $key => $table) {
        $tables[] = $table->{array_keys((array)$table)[0]};
    }
    return in_array($table_name, $tables);
  }

}
