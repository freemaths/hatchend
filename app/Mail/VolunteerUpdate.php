<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class VolunteerUpdate extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    private function set ($v)
    {
    	if (!isset($v->mobile)) $v->mobile='';
    	if (!isset($v->notes)) $v->notes='';
    	if (!isset($v->whatsapp)) $v->whatsapp=false;
    	return $v;
    }
    
    public $v;
    public $o;
    public function __construct($v,$o)
    {
        $this->v = $this->set($v);
        $this->o = $this->set($o);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from("ed@darnell.org.uk")->subject("Volunteer Update")->view('emails.updateVolunteer');
    }
}
