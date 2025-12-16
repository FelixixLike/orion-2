<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Class Namespace
    |--------------------------------------------------------------------------
    |
    | This value sets the root class namespace for Livewire component classes in
    | your application. This value will change where component classes are
    | created when using the filesystem or artisan commands.
    |
    */

    'class_namespace' => 'App\\Livewire',

    /*
    |--------------------------------------------------------------------------
    | View Path
    |--------------------------------------------------------------------------
    |
    | This value sets the root view path for Livewire component views. This value
    | will change where component views are created when using the filesystem
    | or artisan commands.
    |
    */

    'view_path' => resource_path('views/livewire'),

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | The default layout view that will be used when rendering a component via
    | Route::get('/some-endpoint', SomeComponent::class);. In this case,
    | the view returned by SomeComponent will be wrapped in "layouts.app".
    |
    */

    'layout' => 'components.layouts.app',

    /*
    |--------------------------------------------------------------------------
    | Lazy Loading Placeholder
    |--------------------------------------------------------------------------
    |
    | The default placeholder view that will be used when lazy loading a component.
    |
    */

    'lazy_placeholder' => null,

    /*
    |--------------------------------------------------------------------------
    | Temporary File Uploads
    |--------------------------------------------------------------------------
    |
    | Livewire handles file uploads by storing uploads in a temporary directory
    | before the user manually validates and stores them. Here you may
    | configure the directory, disk, and rules for these uploads.
    |
    */

    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => 'file|max:512000', // 500MB
        'directory' => 'livewire-tmp',
        'middleware' => null,
        'preview_mimes' => [
            'png',
            'gif',
            'bmp',
            'svg',
            'wav',
            'mp4',
            'mov',
            'avi',
            'wmv',
            'mp3',
            'm4a',
            'jpg',
            'jpeg',
            'mpga',
            'webp',
            'wma',
        ],
        'max_upload_time' => 60, // 60 minutes
    ],

    /*
    |--------------------------------------------------------------------------
    | Manifest File Path
    |--------------------------------------------------------------------------
    |
    | This value sets the path to the Livewire manifest file.
    |
    */

    'manifest_path' => null,

    /*
    |--------------------------------------------------------------------------
    | Back-button Handling
    |--------------------------------------------------------------------------
    |
    | This value configures how Livewire handles the back button.
    |
    */

    'back_button_cache' => false,

    /*
    |--------------------------------------------------------------------------
    | Render On Redirect
    |--------------------------------------------------------------------------
    |
    | This value configures whether Livewire should render the component
    | before redirecting.
    |
    */

    'render_on_redirect' => false,

    /*
    |--------------------------------------------------------------------------
    | Asset URL
    |--------------------------------------------------------------------------
    |
    | This value sets the URL for Livewire assets.
    |
    */
    // 'asset_url' => null,

    // Legacy support for older Livewire configs if needed
    'temporary_file_upload_rules' => ['file', 'max:512000'],
];
