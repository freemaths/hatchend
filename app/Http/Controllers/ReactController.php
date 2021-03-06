<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Volunteer;
use App\Competitor;
use App\Role;
use App\Swim;
use Log;
use DB;
use Hash;
use App\Mail\VolunteerWelcome;
use App\Mail\VolunteerAdd;
use App\Mail\VolunteerUpdate;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use Storage;

class ReactController extends Controller
{
	public function ajax(Request $request)
	{
		if($request->ajax()){
			if (isset($request->volunteers)) return ($this->volunteers($request));
			else if (isset($request->swim)) return ($this->swim($request));
			else if (isset($request->results)) return ($this->results($request));
			else if (isset($request->competitors)) return ($this->competitors($request));
			else if (isset($request->save)) $this->save($request); // fall through to return updates
			else if (isset($request->saveC)) return ($this->saveC($request)); 
			//else if (isset($request->saveCs)) return ($this->saveCs($request)); 
			else if (isset($request->roles)) $this->roles($request);
			else if (isset($request->login)) return ($this->login($request));
			else if (isset($request->vid)) return ($this->vid($request));
			else if (isset($request->new)) $this->newVolunteer($request);
			else if (isset($request->volAll)) return ($this->volAll($request)); // for CSV files
			else if (isset($request->compAll)) return ($this->compAll($request)); // for CSV files
			//else if (isset($request->sendEmails)) return ($this->sendEmails($request));
			else Log::debug('ajax GET');
			/* breaking old code - will need to fix
			$latest=DB::table('roles')->max('id');
			Log::debug("roles",['id'=>$latest]);
			$comps=$this->get_comps();
			return response()->json(['csrf'=>csrf_token(),'competitors'=>$comps,'scheduled'=>$this->scheduled($comps),'volunteers'=>$this->get_vols(),'roles'=>$latest?Role::find($latest):null]);
			*/
			$latest=DB::table('roles')->max('id');
			return response()->json(['csrf'=>csrf_token(),'volunteers'=>$this->get_vols(),'roles'=>$latest?Role::find($latest):null]);//,'competitors'=>$this->get_comps()]);
				
			//return response()->json(['csrf'=>csrf_token()]);
		}
	}
	
	private function swim($request)
	{
		$ts=round(microtime(true)*1000);
		if ($swim=$request->swim)
		{
			Log::debug('swim',['swim'=>$swim,'key'=>$swim['key'],'last_id'=>$swim['last_id']]);
			$s = new Swim;
			$swim['tss']=$ts;
			$swim['ts']=$request->ts;
			$swim['d2']=$request->d2;
			$s->json=json_encode($swim);
			if (!$swim['key']) {
				$s->token=0; // settings
				$log=[];
			}
			else {
				$s->token=$swim['key'];
				if ($swim['last_id']) $log=Swim::where('token',$swim['key'])->where('id','>',$swim['last_id'])->get();
				else {
					Log::debug('swim link');
					$log=Swim::find($swim['key']);
				}
			}
			if (isset($swim['get'])) {
				$id=DB::table('swims')->max('id');
			}
			else {
				$s->save();
				$id=$s->id;
			}
			return response()->json(['id'=>$id,'log'=>$log, 'tss'=>$ts, 'ts'=>$request->ts, 'd2'=>$request->d2]);
		}
	}
	
	
	private function volAll($request)
	{
		if ($request->volAll['email'] === 'ed@darnell.org.uk')
		{
			$ret=[];
			$vs=Volunteer::all();
			foreach($vs as $v) {
				$vol=json_decode($v->json);
				$vol->id=$v->id;
				$vol->updated_at=$v->updated_at->format('d M Y H:i:s');
				$ret[]=$vol;	
			}
			return response()->json(['volunteers'=>$ret]);
		}
	}
	
	private function results($request)
	{
		$results=[];
		foreach (Storage::files('results') as $file){
			$results[$file]=Storage::get($file);
			if (!mb_detect_encoding($results[$file], 'UTF-8', true)) $results[$file]=utf8_encode($results[$file]);
		}
		Log::debug("results",['results'=>array_keys($results)]);
		return response()->json(['results'=>$results]);
	}
	
	private function compAll($request)
	{
		if ($request->compAll['email'] === 'ed@darnell.org.uk')
		{
			$ret=[];
			$cs=Competitor::all();
			foreach($cs as $c) $ret[]=json_decode($c->json);
			return response()->json(['competitors'=>$ret]);
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
	
	private function scheduled($cs)
	{
		$scheduled=true;
		foreach($cs as $c) {
			$scheduled=$scheduled && isset($c['n']);
		}
		return $scheduled;
	}
	
	private function get_comps($priv=true)
	{

		$cs=Storage::get('competitors/competitors2018.csv');
		if (!mb_detect_encoding($cs, 'UTF-8', true)) $cs=utf8_encode($cs);
		Log::debug("get_comps"); //,['cs'=>$cs]);
		return $cs;
		/*
		$cs=Competitor::all();
		foreach($cs as $c) {
			$cr=[];
			$cr['id']=$c->id;
			$comp=json_decode($c->json,true);
			if (isset($comp['forename'])) $cr['forename']=$comp['forename']; else $cr['forename']=$comp['Forename'];
			if (isset($comp['surname'])) $cr['surname']=$comp['surname']; else $cr['surname']=$comp['Surname'];
			if (isset($comp['n'])) $cr['n']=$comp['n'];
			if (isset($comp['briefing'])) $cr['briefing']=$comp['briefing'];
			if (isset($comp['start'])) $cr['start']=$comp['start'];
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
		*/
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
		$cs=Storage::put('competitors/competitors.json',json_encode($request->saveC));
		return response()->json($request->saveC);
		/*
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
		*/
	}
	
	
	private function saveCs($request)
	{
		Log::debug('ajax saveCs',['saveCs'=>$request->saveCs]);
		if ($cs=Competitor::all()) {
			$new=[];
			foreach ($request->saveCs as $c) $new[$c['id']]=$c;
			foreach($cs as $c)
			{
				$old=json_decode($c->json,true);
				foreach ($new[$c->id] as $key=>$val)
				{
					if (!isset($old[$key]) || $old[$key] !== $val) $old[$key]=$val;
				}
				if (isset($old['surname'])){ $old['Surname']=$old['surname']; unset($old['surname']);}
				if (isset($old['forename'])){ $old['Forename']=$old['forename']; unset($old['forename']);}
				if (isset($old['gender'])){ $old['Gender']=$old['gender']; unset($old['gender']);}
				if (isset($old['ageGroup'])){ $old['AgeGroup']=$old['ageGroup']; unset($old['ageGroup']);}
				$c->json=json_encode($old);
				$c->save();
			}
			$comps=$this->get_comps();
			return response()->json(['competitors'=>$comps,'scheduled'=>$this->scheduled($comps)]);
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
				//Mail::to($vol->email)->bcc("ed@darnell.org.uk")->later($when,new VolunteerWelcome($vol));
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
