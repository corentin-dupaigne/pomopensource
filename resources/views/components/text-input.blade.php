@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-white/30 focus:border-white focus:ring-white/50 rounded-md shadow-sm text-base']) !!}>
