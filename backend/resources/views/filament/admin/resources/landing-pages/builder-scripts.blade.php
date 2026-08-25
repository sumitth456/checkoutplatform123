@php
    $builderJsVersion = @filemtime(public_path('js/landing-page-builder.js')) ?: 1;
@endphp
<script src="https://cdn.jsdelivr.net/npm/monaco-editor@0.52.2/min/vs/loader.js"></script>
<script src="{{ asset('js/landing-page-builder.js') }}?v={{ $builderJsVersion }}"></script>
