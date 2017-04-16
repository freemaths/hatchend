<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Volunteer;
use App\Role;
use Log;
use DB;
use Hash;

class ReactController extends Controller
{
	public function ajax(Request $request)
	{
		if($request->ajax()){
			if (isset($request->volunteers)) return ($this->volunteers($request));
			else if (isset($request->save)) $this->save($request); // fall through to return updates
			else if (isset($request->roles)) $this->roles($request);
			else if (isset($request->login)) return ($this->login($request));
			else if (isset($request->vid)) return ($this->vid($request));
			else if (isset($request->new)) $this->newVolunteer($request);
			else Log::debug('ajax GET');
			$latest=DB::table('roles')->max('id');
			Log::debug("roles",['id'=>$latest]);
			return response()->json(['csrf'=>csrf_token(),'volunteers'=>$this->get_vols(),'roles'=>$latest?Role::find($latest):null]);
		}
	}
	
	private function get_vols($priv=true)
	{
		$ret=[];
		$vs=Volunteer::all();
		foreach($vs as $v) {
			$vol=json_decode($v->json);
			$vol->id=$v->id;
			unset($vol->key);
			if ($priv) {
				unset($vol->email);
				unset($vol->mobile);
				unset($vol->whatsapp);
				unset($vol->whatsapp);
				unset($vol->role);
			}
			$ret[]=$vol;
		}
		return $ret;
	}
	
	private function volunteers($request)
	{
		Log::debug('ajax volunteers');
		$ids=[];
		foreach($request->volunteers as $volunteer)
		{
			$v = new Volunteer;
			$v->json=json_encode($volunteer);
			$v->save();
			$ids[]=['name'=>$volunteer->name,'id'=>$v->id];
		}
		return response()->json(['ids'=>$ids]);
	}
	
	private function roles($request)
	{
		Log::debug('ajax roles');
		$r = new Role;
		$r->json=json_encode($request->roles);
		$r->save();
	}
	
	private function save($request)
	{
		Log::debug('ajax save',['save'=>$request->save]);
		if ($v=Volunteer::where('id',$request->save['id'])->first()) {
			if (isset($request->save['password'])) $request->save['password'] = Hash::make($request->save['password']);
			$v->json=json_encode($request->save);
			$v->save();
		}
	}
	
	private function newVolunteer($request)
	{
		Log::debug('ajax new',['new'=>$request->new]);
		$v = new Volunteer;
		$v->json=json_encode($request->new);
		$v->save();
	}
	
	private function get_v($vid)
	{
		if ($v=Volunteer::where('id',$vid)->first()) {
			$vol=json_decode($v->json);
			$vol->id=$v->id;
		}
		else $vol = null;
		return $vol;
	}
	
	private function login($request)
	{
		if ($vol=$this->get_v($request->login)) {
			if ($request->code == $vol->key || (isset($vol->password) && Hash::check($request->code,$vol->password))) {
				$v=$vol;
			}
			else $v=null;
		}
		else $v=null;
		Log::debug('ajax login',['volunteer'=>$v]);
		return response()->json(['volunteer'=>$v]);
	}
	
	private function vid($request)
	{
		if ($vol=$this->get_v($request->id)) {
			if ($request->code == $vol->key || (isset($vol->password) && Hash::check($request->code,$vol->password))) {
				if ($vol->admin == 'full' && isset($request->vid))
				{
					$vol=$this->get_v($request->vid);
				}
				$v=$vol; 
			}
			else $v=null;
		}
		else $v=null;
		Log::debug('ajax vid',['volunteer'=>$v,'vol'=>$vol,'vid'=>$request->vid,'id'=>$request->id,'code'=>$request->code]);
		return response()->json(['volunteer'=>$v]);
	}
	
}
