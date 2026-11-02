<?php
return ['default'=>env('CACHE_DRIVER','file'),'stores'=>[
 'array'=>['driver'=>'array','serialize'=>false], 'file'=>['driver'=>'file','path'=>storage_path('framework/cache/data'),'lock_path'=>storage_path('framework/cache/data')],
 'database'=>['driver'=>'database','table'=>'cache','connection'=>null,'lock_connection'=>null,'lock_table'=>'cache_locks'],
], 'prefix'=>env('CACHE_PREFIX','alasra_cache_')];
