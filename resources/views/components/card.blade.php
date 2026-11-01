@props(['padding' => 'p-6'])

<div {{ $attributes->merge(['class' => 'concept-panel '.$padding]) }}>
    {{ $slot }}
</div>
