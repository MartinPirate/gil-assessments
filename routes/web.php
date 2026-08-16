<?php

use Illuminate\Support\Facades\Route;

/*
 * The whole application is the Filament panel at /admin — there is no separate
 * public front end, so the root is not a page in its own right. It used to
 * serve Laravel's stock welcome view, which made a running app look unbuilt.
 *
 * Signed out, the panel sends the visitor on to its own login screen.
 */
Route::redirect('/', '/admin')->name('home');
