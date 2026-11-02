<?php
use Illuminate\Support\Facades\Artisan;
Artisan::command('alasra:about', function () { $this->info('Al Asra Store API backend — Laravel 10'); })->purpose('Display Al Asra backend information');
