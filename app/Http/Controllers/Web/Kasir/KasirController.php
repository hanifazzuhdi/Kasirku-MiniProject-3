<?php

namespace App\Http\Controllers\Web\Kasir;

use App\Models\{Barang, Keranjang, Transaksi};

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\{Auth, DB};


class KasirController extends Controller
{
    // Tambah Data ke Keranjang
    public function keranjang()
    {
        DB::beginTransaction();
        // logic transaksi
        $cek_transaksi = Transaksi::transaksiAktif()->first();

        if (empty($cek_transaksi)) {
            $transaksi = Transaksi::create([
                'kasir_id' => Auth::id(),
            ]);
        } else {
            $transaksi = Transaksi::transaksiAktif()->first();
        }

        // logic keranjang
        $barang = Barang::where('uid', request('uid'))->first();

        // Cek Stok
        if ($barang->stok < request('pcs')) {
            return false;
        }

        $cekKeranjang = Keranjang::where('uid', $barang->uid)->first();

        if ($cekKeranjang) {
            $cekKeranjang->pcs = $cekKeranjang->pcs + request('pcs');
            $cekKeranjang->total_harga = $barang->harga_jual * $cekKeranjang->pcs;
            $cekKeranjang->save();

            // Cek Stok
            if ($cekKeranjang->pcs > $barang->stok) {
                return false;
            }
        } else {
            $keranjang = Keranjang::create([
                'uid' => request('uid'),
                'nama_barang' => $barang->nama_barang,
                'harga' => $barang->harga_jual,
                'pcs' => request('pcs'),
                'total_harga' => $barang->harga_jual * request('pcs'),
                'transaksi_id' => $transaksi->id
            ]);
        }

        // jika dia member
        if (request('kode_member')) {

            $transaksi->update([
                'kode_member' => request('kode_member')
            ]);

            $this->member($transaksi, $barang);
        }

        // update total harga transaksi
        $transaksi->update([
            'harga_total' => Keranjang::where('transaksi_id', $transaksi->id)->sum('total_harga')
        ]);

        DB::commit();

        return Keranjang::where('transaksi_id', $transaksi->id)->get();
    }

    // Jika dia member
    public function member($transaksi, $barang)
    {
        // code
        $keranjang = Keranjang::where('transaksi_id', $transaksi->id)->where('uid', $barang->uid)->first();

        $keranjang->update([
            'diskon' => $barang->diskon * $keranjang->pcs,
            'total_harga' => $keranjang->harga * $keranjang->pcs - ($barang->diskon * $keranjang->pcs)
        ]);
    }

    // Bayar melalui Cash
    public function bayar()
    {
        $transaksi = Transaksi::transaksiAktif()->firstOrFail();

        if ($transaksi->harga_total < request('dibayar')) {
            // Jalankan Transaksi
            DB::beginTransaction();
            $this->kurangiStok($transaksi);

            $transaksi->update([
                'dibayar' => request('dibayar'),
                'kembalian' => request('dibayar') - $transaksi->harga_total,
                'status' => 1
            ]);
            DB::commit();
        }

        return $this->sendResponse('success', 'Transaksi berhasil dilakukan', $transaksi, 202);
    }

    // Kurangi Stok Barang
    public function kurangiStok($transaksi)
    {
        $keranjang = Keranjang::where('transaksi_id', $transaksi->id)->get();

        for ($i = 0; $i < count($keranjang); $i++) {
            # code...
            $barang = Barang::where('uid', $keranjang[$i]->uid)->first();

            $barang->update([
                'stok' => $barang->stok - $keranjang[$i]->pcs
            ]);
        }
    }
}
