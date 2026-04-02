@props(['disabled' => false])

<input {{ $disabled ? 'disabled' : '' }} {!! $attributes->merge(['class' => 'border-gray-300 bg-white dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 focus:border-crea-secondary focus:ring-crea-secondary rounded-md shadow-sm']) !!}>
