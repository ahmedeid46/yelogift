<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderCode;
use App\Models\OrderProduct;
use App\Traits\APIHandleClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class OrderController extends Controller
{
    use APIHandleClass;
    /**
     * Display a listing of the resource.
     *
     * This function retrieves all the orders with their associated order products and order codes.
     * It decrypts the code of each order code before returning the data.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Retrieve all orders with their associated order products and order codes
        $orders = Order::with(['OrderProduct','OrderProduct.product','OrderProduct.order_code'])->get();

        // Decrypt the code of each order code
        $orders->each(function ($order) {
            $order->OrderProduct->each(function ($product) {
                $product->order_code->each(function ($code) {
                    $code->code = decrypt($code->code);
                });
            });
        });

        // Set the data to be returned and return the response
        $this->setData($orders);
        return $this->returnResponse();
    }

    public function get($order_id){
        // Retrieve all orders with their associated order products and order codes
        $orders = Order::with(['OrderProduct','OrderProduct.product','OrderProduct.order_code'])->find($order_id);

        // Decrypt the code of each order code
        $orders->each(function ($order) {
            $order->OrderProduct->each(function ($product) {
                $product->order_code->each(function ($code) {
                    $code->code = decrypt($code->code);
                });
            });
        });
        // Set the data to be returned and return the response
        $this->setData($orders);
        return $this->returnResponse();
    }



    /**
     * Store a newly created resource in storage.
     *
     * This function handles the delivery of a code for an order product.
     * It validates the request, checks for any limitations, and stores the code.
     *
     * @param Request $request The HTTP request object.
     *
     * @return \Illuminate\Http\JsonResponse The JSON response.
     */
    public function delivery_code(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'order_id'=>'required|exists:orders,id',
            'order_codes'=>'required|array',
            'order_codes.*.order_product_id' => 'required|exists:order_products,id',
            'order_codes.*.code' => 'required',
        ]);

        // If the validation fails, return the error response
        if ($validator->fails()) {
            $this->setMessage($validator->errors()->first());
            $this->setStatusCode(400);
            $this->setStatusMessage(false);
            return $this->returnResponse();
        }
        DB::beginTransaction();
        foreach($request->order_codes as $order_code){
            // Get the order product and the count of order codes
            $order_product = OrderProduct::find($order_code->order_product_id);
            $order_code = OrderCode::where('order_product_id', $order_code->order_product_id)->count();

            // If the order code count is greater than or equal to the quantity, return an error response
            if ($order_code >= $order_product->quantity) {
                $this->setMessage(__('translate.order_code_limitation'));
                $this->setStatusCode('400');
                $this->setStatusMessage(false);
                return $this->returnResponse();
            }
            $order_code_unique = OrderCode::where('code', encrypt($order_code->code))->first();
            if ($order_code_unique) {
                $this->setMessage(__('translate.order_code_found_later'));
                $this->setStatusCode('400');
                $this->setStatusMessage(false);
                return $this->returnResponse();
            }
            // Create a new order code and save it
            $order = new OrderCode;
            $order->order_product_id = $request->order_product_id;
            $order->code = encrypt($order_code->code);
            $order->save();
        }
        DB::commit();
        // Set the success response
        $this->setMessage(__('translate.order_code_store_success'));
        return $this->returnResponse();
    }
}
