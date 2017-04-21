<body>
<div>Volunteer Update</div>
<div>Name: {{$v->name}} 
@if ($v->name!==$o->name)
({{$o->name}})
@endif
</div>
<div>Email: {{$v->email}} 
@if ($v->email!==$o->email)
({{$o->email}})
@endif
</div>
<div>Mobile: {{$v->mobile}} 
@if ($v->mobile!==$o->mobile)
({{$o->mobile}})
@endif
</div>
<div>WhatsApp: {{$v->whatsapp}} 
@if ($v->whatsapp!==$o->whatsapp)
({{$o->whatsapp}})
@endif
</div>
<div>Notes: {{$v->notes}} 
@if ($v->notes!==$o->notes)
({{$o->notes}})
@endif
</div>
</body>