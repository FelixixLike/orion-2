@props([
    'name',
    'type' => 'text',
    'placeholder' => '',
    'value' => '',
    'required' => false,
    'icon' => 'user',
    'label' => null,
    'showError' => true,
])

<div class="form-field">
    @if ($label)
        <label for="{{ $name }}" class="sr-only">{{ $label }}</label>
    @endif

    <div class="relative">
        <div class="absolute p-3 inset-y-0 left-0 flex items-center pointer-events-none" aria-hidden="true">
            @if ($icon === 'user')
                @include('components.svg.user')
            @elseif($icon === 'lock')
                @include('components.svg.lock')
            @endif
        </div>
        <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}" value="{{ $type !== 'password' ? $value : '' }}"
            @if ($required) required aria-required="true" @endif
            @if ($type === 'number') min="0" step="1" @endif
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" data-has-error="true" @enderror
            class="w-full bg-white/3 rounded-xs border @error($name) border-red-400 ring-1 ring-red-400/50 @else border-white/80 @enderror text-white text-sm pl-10 {{ $type === 'password' ? 'pr-10' : 'pr-3' }} py-2.5 focus:outline-none focus:ring-1 @error($name) focus:ring-red-400 focus:border-red-400 @else focus:ring-white focus:border-white @enderror placeholder-white/70 autofill-fix"
            placeholder="{{ $placeholder }}"
            @if ($type === 'number') onkeydown="if(['ArrowUp','ArrowDown','e','E','+','-'].includes(event.key)) event.preventDefault();" @endif
            {{ $attributes }}>

        @if ($type === 'password')
            <button type="button" onclick="togglePassword('{{ $name }}')"
                class="absolute rounded-xs text-sm inset-y-0 right-0 pr-3 flex items-center text-white/70 hover:text-white transition-colors"
                aria-label="Mostrar contraseña">
                @include('components.svg.eye')
            </button>
        @endif
    </div>

    @if ($showError)
        @error($name)
            <p id="{{ $name }}-error" class="mt-1.5 text-xs text-red-300 font-medium" role="alert">
                {{ $message }}
            </p>
        @enderror
    @endif
</div>
