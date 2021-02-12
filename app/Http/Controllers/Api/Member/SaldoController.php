<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SaldoController extends Controller
{
    public function __construct()
    {
        return $this->middleware('jwt.auth')->except('webhooks');
    }

    /**
     * Method get saldo
     *
     */
    public function index()
    {
        $data = Member::where('id', auth('member')->id())->first();

        return $this->sendResponse('success', 'Saldo berhasil dimuat', $data->saldo, 200);
    }

    /**
     * Method get transaksi
     *
     */
    public function transaksi()
    {
        $data = Payment::where('kode_member', auth('member')->user()->kode_member)->get();

        return $this->sendResponse('success', 'Riwayat transaksi berhasil dimuat', $data, 200);
    }

    /**
     * Method for add saldo
     *
     */
    public function store(Request $request)
    {
        $order_id = Str::upper($request->input('bank')) . "-" . date('dmyHis') . '-' . auth('member')->id();

        DB::beginTransaction();
        $res = Http::withBasicAuth('SB-Mid-server-P_D1Q6IGgH4b-_YqgY6Ybnra', '')
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ])
            ->post('https://api.sandbox.midtrans.com/v2/charge', [
                "payment_type" => "bank_transfer",

                "transaction_details" => [
                    "gross_amount" => $request->input('jumlah'),
                    "order_id" => $order_id
                ],

                "customer_details" => [
                    "email" => auth('member')->user()->kode_member,
                    "first_name" => auth('member')->user()->nama,
                    "phone" => auth('member')->user()->nomor
                ],

                "bank_transfer" => [
                    "bank" => $request->input('bank'),
                ]
            ]);

        Payment::create([
            'order_id' => $order_id,
            'jumlah' => $request->input('jumlah'),
            "kode_member" => auth('member')->user()->kode_member,
            "nama_member" => auth('member')->user()->nama,
            "nomor_member" => auth('member')->user()->nomor,
            'bank' => $request->input('bank')
        ]);
        DB::commit();

        $data = $res->json();

        return response()->json([
            'status' => 'success',
            'message' => 'Transaksi berhasil dilakukan',
            'data'  => [
                'order_id' => $data['order_id'],
                'transaction_status' => $data['transaction_status'],
                'jumlah' => $data['gross_amount'],
                'va_numbers' => $data['va_numbers'],
                'transaction_date' => $data['transaction_time']
            ]
        ]);
    }

    /**
     *  Webhooks for midtrans transaction
     *
     */
    public function webhooks(Request $request)
    {
        $notification_body = json_decode($request->getContent(), true);

        $order_id = $notification_body['order_id'];

        $transaction_status = $notification_body['transaction_status'];

        $status = $notification_body['status_code'];

        $jumlah = explode('.', $notification_body['gross_amount'])[0];

        if ($transaction_status == 'settlement' and $status == '200') {

            $payment = Payment::where('order_id', $order_id)->first();

            $payment->update([
                'status' => 1
            ]);

            $id_member = explode('-', $order_id);

            $member = Member::where('id', $id_member[2])->first();

            $member->update([
                'saldo' => $member->saldo + $jumlah
            ]);
        } else if ($transaction_status == 'expire' || $transaction_status == 'cancel') {
            $payment = Payment::where('order_id', $order_id)->first();

            $payment->update([
                'status' => 2
            ]);
        }

        return;
    }
}
