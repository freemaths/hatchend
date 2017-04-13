<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Volunteer;
use App\Role;
use Log;
use DB;

class ReactController extends Controller
{
	public function ajax(Request $request)
	{
		if($request->ajax()){
			if (isset($request->volunteers)) return ($this->volunteers($request));
			else if (isset($request->roles)) return ($this->roles($request));
			else if (isset($request->code)) return ($this->verify($request));
			else Log::debug('ajax GET');
			$latest=DB::table('roles')->max('id');
			return response()->json(['csrf'=>csrf_token(),'volunteers'=>Volunteer::select('id','name')->get(),'roles'=>$latest?Role::find($latest)->get():null]);
		}
	}
	
	private function volunteers($request)
	{
		Log::debug('ajax volunteers');
		$ids=[];
		foreach($request->volunteers as $volunteer)
		{
			$v = new Volunteer;
			$v->name=$volunteer['name'];
			$v->json=json_encode($volunteer);
			$v->save();
			$ids[]=['name'=>$v->name,'id'=>$v->id];
		}
		return response()->json(['ids'=>$ids]);
	}
	
	private function roles($request)
	{
		Log::debug('ajax roles');
		$r = new Role;
		$r->json=json_encode($request->roles);
		$r->save();
		return response()->json(['id'=>$r->id]);
	}
	
	private function verify($request)
	{
		if ($v=Volunteer::where('id',$request->id)->first()) {
			$vol=json_decode($v->json);
			$vol->id=$v->id;
			if ($request->code == $vol->key) $v=$vol; 
			else $v=null;
		}
		else $v=null;
		Log::debug('ajax verify',['volunteer'=>$v]);
		return response()->json(['volunteer'=>$v]);
	}
	
}
