<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Resources;

class HomeController extends ResourcesController
{

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct(Request $request, Resources $model) {
      try {
        $this->middleware('auth');
        $this->model = $model;
        $this->table_name = $request->segment(1);
        $this->generateBreadcrumbs($request->segments());
        $this->segments = $request->segments();
      } catch (\Exception $e) {

      } finally {
      }

    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return view('home.index')->with($this->respondWithData());
    }

}
