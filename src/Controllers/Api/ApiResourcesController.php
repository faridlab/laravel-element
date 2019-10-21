<?php

namespace Molecule\Controllers\Api;

use Illuminate\Http\Request;
use Molecule\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Models\Resources;
use Yajra\Datatables\Datatables;

class ApiResourcesController extends Controller
{
  protected $table_name = null;
  protected $model = null;
  protected $structures = array();
  protected $segments = [];
  protected $segment = null;

  public $response = array();

  /**
   * Create a new AuthController instance.
   *
   * @return void
   */
  public function __construct(Request $request, Resources $model) {

    try {
      $this->segment = $request->segment(3);
      if(file_exists(app_path('Models/'.studly_case($this->segment)).'.php')) {
        $this->model = app("App\Models\\".studly_case($this->segment));
      } else {
        if($model->checkTableExists($this->segment)) {
          $this->model = $model;
          $this->model->setTable($this->segment);
        }
      }

      if($this->model) {
        $this->structures = $this->model->getStructure();
      }
      $this->table_name = $this->segment;
      $this->segments = $request->segments();
      $this->response = array(
        'app' => config('app.name'),
        'version' => config('app.version', 1),
        'api_version' => config('api.version', 1),
        'status' => 'success',
        'collection' => studly_case($this->table_name),
        'code' => 200,
        'message' => null,
        'errors' => [],
        'data' => [],
        'meta' => null,
      );

    } catch (\Exception $e) {}
  }

  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index(Request $request) {
    try {

      $limit = intval($request->get('limit', 25));
      if($limit > 100) {
        $limit = 100;
      }

      $p = intval($request->get('page', 1));
      $page = ($p > 0 ? $p - 1: $p);

      if($request->get('search')) {
        $searchable = $this->model->getSearchable();
        foreach ($searchable as $field) {
          $this->model = $this->model->orWhere($field, 'LIKE', '%' . trim($request->get('search')) . '%');
        }
      }

      // FIXME: this line below not running
      $fields = $request->except(['page', 'limit', 'with', 'search']);
      if(count($fields)) {
        foreach ($fields as $field => $value) {
          $this->model = $this->model->where($field, $request->get($value));
        }
      }

      if($request->has('with')) {
        $relations = explode(',', $request->get('with'));
        $this->model = $this->model->with($relations);
      }

      $data = $this->model
                    ->offset($page * $limit)
                    ->limit($limit)
                    ->get();

      $this->response['code'] = 200;
      $this->response['status'] = 'OK';
      $this->response['message'] = 'Data retrieved';
      $this->response['data'] = $data;

      return response()->json($this->response, 200);

    } catch(\Exception $e) {
      $this->response['code'] = $e->status;
      $this->response['status'] = $e->getMessage();
      $this->response['message'] = $e->getMessage();
      $this->response['data'] = [];
      $this->response['errors'] = [];

      return response()->json($this->response, $e->status);
    }
  }

  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function trash(Request $request) {
    try {
      $this->model = $this->model->onlyTrashed();
      $limit = intval($request->get('limit', 25));
      if($limit > 100) {
        $limit = 100;
      }

      $p = intval($request->get('page', 1));
      $page = ($p > 0 ? $p - 1: $p);

      if($request->get('search')) {
        $columns = array();
        foreach ($this->structures as $field) {
          if($field['display']) {
            $this->model = $this->model->orWhere($field['field'], 'LIKE', '%' . trim($request->get('search')) . '%');
          }
        }
      }

      $data = $this->model
                    ->offset($page * $limit)
                    ->limit($limit)
                    ->get();

      $this->response['code'] = 200;
      $this->response['status'] = 'OK';
      $this->response['message'] = 'Data retrieved';
      $this->response['data'] = $data;

      return response()->json($this->response, 200);

    } catch(\Exception $e) {
      $this->response['code'] = $e->status;
      $this->response['status'] = $e->getMessage();
      $this->response['message'] = $e->getMessage();
      $this->response['data'] = [];
      $this->response['errors'] = [];

      return response()->json($this->response, $e->status);
    }
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request)
  {
    try {
      $validator = $this->model->validator($request);
      if ($validator->fails() && $request->ajax()) {
        $this->response['errors'] = $validator->errors();
        $this->response['code'] = 403;
        $this->response['message'] = $validator->errors()->first();
        return response()->json($this->response);
      }

      $validator->validate();
      foreach ($request->all() as $key => $value) {
        if(starts_with($key, '_')) continue;
        $this->model->setAttribute($key, $value);
      }
      $this->model->save();
      $this->response['message'] = title_case(str_singular($this->table_name)).' created!';
      $this->response['data'] = $this->model;
      return response()->json($this->response);
    } catch (\Exception $e) {
      $this->response['errors'] = $validator->errors();
      $this->response['message'] = $validator->errors()->first();
      return response()->json($this->response, $e->getCode());
    }
  }

  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function show($id)
  {
    try {
      $data = $this->model->find($id);

      $this->response['code'] = 200;
      $this->response['status'] = 'OK';
      $this->response['message'] = 'Data retrieved';
      $this->response['data'] = $data;

      return response()->json($this->response, 200);

    } catch(\Exception $e) {

      $this->response['code'] = $e->status;
      $this->response['status'] = $e->getMessage();
      $this->response['message'] = $e->getMessage();
      $this->response['data'] = [];
      $this->response['errors'] = [];

      return response()->json($this->response, $e->status);
    }

  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request, $id)
  {
    try {
      // Change rules of unique column
      $rules = $this->model->getRules();
      foreach ($rules as $key => $value) {
        if(str_contains($value, 'unique')) {
          $terms = explode('|', $value);
          foreach ($terms as $index => $term) {
            if(str_contains($term, 'unique')) $terms[$index] = $term .",$key,".$id;
          }
          $rules[$key] = implode('|', $terms);
        }
      }
      $this->model->setRules($rules);
      $this->model->validator($request)->validate();
      $model = $this->model::find($id);
      foreach ($request->all() as $key => $value) {
        if(starts_with($key, '_')) continue;
        $model->setAttribute($key, $value);
      }
      $model->save();

      $this->response['code'] = 200;
      $this->response['status'] = 'OK';
      $this->response['message'] = 'Data updated';
      $this->response['data'] = $model;

      return response()->json($this->response, 200);
    } catch (\Exception $e) {
      $this->response['code'] = $e->status;
      $this->response['status'] = $e->getMessage();
      $this->response['message'] = $e->getMessage();
      $this->response['data'] = [];
      $this->response['errors'] = [];

      return response()->json($this->response, $e->status);
    }
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function patch(Request $request, $collection, $id)
  {
    try {
      // Change rules of unique column
      $rules = $this->model->getRules();
      foreach ($rules as $key => $value) {
        if(str_contains($value, 'unique')) {
          $terms = explode('|', $value);
          foreach ($terms as $index => $term) {
            if(str_contains($term, 'unique')) $terms[$index] = $term .",$key,".$id;
          }
          $rules[$key] = implode('|', $terms);
        }
      }
      $this->model->setRules($rules);
      $this->model->validator($request)->validate();
      $model = $this->model::find($id);
      foreach ($request->all() as $key => $value) {
        if(starts_with($key, '_')) continue;
        $model->setAttribute($key, $value);
      }
      $model->save();

      $this->response['code'] = 200;
      $this->response['status'] = 'OK';
      $this->response['message'] = 'Data updated';
      $this->response['data'] = $model;

      return response()->json($this->response, 200);
    } catch (\Exception $e) {
      $this->response['code'] = $e->status;
      $this->response['status'] = $e->getMessage();
      $this->response['message'] = $e->getMessage();
      $this->response['data'] = [];
      $this->response['errors'] = [];

      return response()->json($this->response, $e->status);
    }
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function destroy($id)
  {
      //
  }
}
