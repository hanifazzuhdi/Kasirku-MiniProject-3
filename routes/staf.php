<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Staf Routes
|--------------------------------------------------------------------------
*/

// Pembelian
Route::get('/daftar-pembelian', 'StaffController@index')->name('staf.pembelian');
Route::get('/tambah-pembelian', 'StaffController@create')->name('staf.pembelian.create');
Route::get('/cetak-pembelian/{pembelian}', 'StaffController@cetak')->name('pembelian.cetak');
Route::post('/daftar-pembelian', 'StaffController@cari')->name('staf.pembelian.cari');
Route::post('/tambah-supplier', 'StaffController@addSupplier')->name('staf.pembelian.addSupplier');
Route::post('/tambah-pembelian', 'StaffController@store')->name('staf.pembelian.store');

// Produk
Route::get('/daftar-produk', 'ProdukController@index')->name('staf.produk');
Route::post('/daftar-produk', 'ProdukController@cari')->name('staf.produk.cari');
