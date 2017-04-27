<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Volunteer;
use App\Competitor;
use App\Role;
use Log;
use DB;
use Hash;
use App\Mail\VolunteerWelcome;
use App\Mail\VolunteerAdd;
use App\Mail\VolunteerUpdate;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class ReactController extends Controller
{
	public function ajax(Request $request)
	{
		if($request->ajax()){
			if (isset($request->volunteers)) return ($this->volunteers($request));
			else if (isset($request->competitors)) return ($this->competitors($request));
			else if (isset($request->save)) $this->save($request); // fall through to return updates
			else if (isset($request->saveC)) return ($this->saveC($request)); // fall through to return updates
			else if (isset($request->roles)) $this->roles($request);
			else if (isset($request->login)) return ($this->login($request));
			else if (isset($request->vid)) return ($this->vid($request));
			else if (isset($request->new)) $this->newVolunteer($request);
			else if (isset($request->sendEmails)) return ($this->sendEmails($request));
			else Log::debug('ajax GET');
			$latest=DB::table('roles')->max('id');
			Log::debug("roles",['id'=>$latest]);
			return response()->json(['csrf'=>csrf_token(),'competitors'=>$this->get_comps(),'volunteers'=>$this->get_vols(),'roles'=>$latest?Role::find($latest):null]);
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
	
	private function get_comps($priv=true)
	{
		$ret=[];
		$cs=Competitor::all();
		foreach($cs as $c) {
			$comp=json_decode($c->json,true);
			$cr=[];
			$cr['id']=$c->id;
			if (isset($comp['forename'])) $cr['forename']=$comp['forename']; else $cr['forename']=$comp['Forename'];
			if (isset($comp['surname'])) $cr['surname']=$comp['surname']; else $cr['surname']=$comp['Surname'];
			$cr['gender']=$comp['Gender'];
			$cr['ageGroup']=$comp['AgeGroup'];
			if (isset($comp['swim'])) $cr['swim']=$comp['swim'];
			else foreach ($comp as $key =>$value) {
				if (ends_with($key,'swim_time'))
				{
					$cr['swim']=$value;
					$cr['estimate']=$key;
				}
			}
			$ret[]=$cr;
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
			$old=json_decode($v->json);
			if (isset($request->save['password'])) $request->save['password'] = Hash::make($request->save['password']);
			$v->json=json_encode($request->save);
			$v->save();
			$vol=json_decode($v->json);
			$vol->id=$v->id;
			Mail::to("ed@darnell.org.uk")->queue(new VolunteerUpdate($vol,$old));
		}
	}
	
	private function saveC($request)
	{
		Log::debug('ajax saveC',['saveC'=>$request->saveC]);
		if ($c=Competitor::where('id',$request->saveC['id'])->first()) {
			$old=json_decode($c->json,true);
			foreach ($request->saveC as $key=>$val)
			{
				if (!isset($old[$key]) || $old[$key] !== $val) $old[$key]=$val;
			}
			$c->json=json_encode($old);
			$c->save();
			return response()->json(['competitors'=>$this->get_comps()]);
		}
	}
	
	private function newVolunteer($request)
	{
		Log::debug('ajax new',['new'=>$request->new]);
		$v = new Volunteer;
		$v->json=json_encode($request->new);
		$v->save();
		$vol=json_decode($v->json);
		$vol->id=$v->id;
		Mail::to($vol->email)->bcc("ed@darnell.org.uk")->queue(new VolunteerAdd($vol));
	}
	
	private function sendEmails($request)
	{
		Log::debug('ajax sendEmails');
		$d=0;
		$vs=Volunteer::all();
		foreach ($vs as $v)
		{
			$delay = $d*10;
			$when = Carbon::now()->addSeconds($delay);
			$d++;
			$vol=json_decode($v->json);
			$vol->id=$v->id;
			if (isset($vol->email)) {
				Log::debug('sendEmail',['email'=>$vol->email,'delay'=>$delay,'when'=>$when]);
				//Mail::to("ed@darnell.org.uk")->bcc("ed@darnell.org.uk")->later($when,new VolunteerWelcome($vol));
				Mail::to($vol->email)->bcc("ed@darnell.org.uk")->later($when,new VolunteerWelcome($vol));
			}
			else Log::debug('sendEmail unset email',['id'=>$vol->id,'delay'=>$delay,'when'=>$when]);
		}
		//Mail::to($vol->email)->bcc("ed@darnell.org.uk")->queue(new VolunteerAdd($vol));
		return response()->json(['sent'=>$d]);
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
		/*if ($v->email == 'ed@darnell.org.uk') {
			Mail::to("ed@darnell.org.uk")->queue(new VolunteerWelcome($v));
		}*/
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
