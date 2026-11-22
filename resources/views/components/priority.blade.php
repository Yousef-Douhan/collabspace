@props(['value'])
@php $v = $value instanceof \BackedEnum ? $value->value : $value; @endphp
<span @class([
    'chip',
    'bg-rose-50 text-rose-600' => $v === 'high',
    'bg-amber-50 text-amber-600' => $v === 'medium',
    'bg-emerald-50 text-emerald-600' => $v === 'low',
])>{{ __(ucfirst($v)) }}</span>
