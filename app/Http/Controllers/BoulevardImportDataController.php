<?php

namespace App\Http\Controllers;

use App\Brands;
use App\Business;
use App\BusinessLocation;
use App\Category;
use App\Exports\ProductsExport;
use App\Media;
use App\Product;
use App\ProductVariation;
use App\PurchaseLine;
use App\SellingPriceGroup;
use App\TaxRate;
use App\Transaction;
use App\Unit;
use App\Utils\ModuleUtil;
use App\Utils\ProductUtil;
use App\Variation;
use App\VariationGroupPrice;
use App\VariationLocationDetails;
use App\VariationTemplate;
use App\Warranty;
use Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Yajra\DataTables\Facades\DataTables;
use App\Events\ProductsCreatedOrModified;

class BoulevardImportDataController extends Controller
{
    /**
     * All Utils instance.
     */
    protected $productUtil;

    protected $moduleUtil;

    /**
     * Constructor
     *
     * @param  ProductUtils  $product
     * @return void
     */
    public function __construct(ProductUtil $productUtil, ModuleUtil $moduleUtil)
    {
        $this->productUtil = $productUtil;
        $this->moduleUtil = $moduleUtil;

        $this->authHeader = $this->generateAuthHeader(
            config('services.blvd_api.business_id'),
            config('services.blvd_api.secret'),
            config('services.blvd_api.key')
        );
        $this->api_base_blvd_url = config('services.blvd_api.api_base_url');
    }
    
    private function generateAuthHeader($business_id, $api_secret, $api_key)
    {
        $timestamp = strtotime("now");
        $prefix = 'blvd-admin-v1';
        $payload = $prefix . $business_id . $timestamp;

        $raw_key = base64_decode(strtr($api_secret, '._-', '+/='));
        $raw_mac = hash_hmac('sha256', $payload, $raw_key, true);
        $signature = base64_encode($raw_mac);

        $token = $signature . $payload;

        $basic_payload = $api_key . ':' . $token;
        $basic_credentials = base64_encode($basic_payload);

        return 'Basic ' . $basic_credentials;
    }

    public function index()
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }
        
        return view('import_products.index_boulevard');
    }
    public function store(Request $request)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {

            //Set maximum php execution time
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', -1);

            $start_process = $request->input('start_process');
            if ($start_process == "start") {
                // Fetch the initial page
                $response_from_func = $this->fetchProducts();
                if($response_from_func['success'] == 0)
                {
                    return redirect('import-boulevard-products')->with('notification', $response_from_func);
                }
                else
                {
                    $response = $response_from_func['msg'];
                }
                $products = $response['data']['products']['edges'];
                $pageInfo = $response['data']['products']['pageInfo'];

                while ($pageInfo['hasNextPage']) {
                    $endCursor = $pageInfo['endCursor'];
                    $response_from_func = $this->fetchProducts($endCursor);
                    if($response_from_func['success'] == 0)
                    {
                        return redirect('import-boulevard-products')->with('notification', $response_from_func);
                    }
                    else
                    {
                        $response = $response_from_func['msg'];
                    }
                    $products = array_merge($products, $response['data']['products']['edges']);
                    $pageInfo = $response['data']['products']['pageInfo'];
                }
            
                $business_id = $request->session()->get('user.business_id');
                $user_id = $request->session()->get('user.id');
                $default_profit_percent = $request->session()->get('business.default_profit_percent');
                $business_locations = BusinessLocation::where('business_id', $business_id)->get();
                $formated_data = [];

                $is_valid = true;
                $error_msg = '';

                $total_rows = count($products);
                
                DB::beginTransaction();

                // echo "<pre>";
                // print_r($products);
                // echo "</pre>";
                // exit;

                foreach ($products as $key => $value) {

                    $row_no = $key + 1;
                    $product_array = [];
                    $product_array['business_id'] = $business_id;
                    $product_array['created_by'] = $user_id;
                    $product_array['bvpId'] = trim($value['node']['id']);

                    $product_name = trim($value['node']['name']);
                    if (! empty($product_name)) {
                        $product_array['name'] = $product_name;
                    } else {
                        // $is_valid = false;
                        // $error_msg = "Product name is required in row no. $row_no";
                        // break;
                    }
                    $product_array['product_description'] = isset($value['node']['description']) ? $value['node']['description'] : null;
                    $product_array['barcode'] = isset($value['node']['barcode']) ? $value['node']['barcode'] : null;
                    $product_array['color'] = isset($value['node']['color']) ? $value['node']['color'] : null;
                    $product_array['size'] = isset($value['node']['size']) ? $value['node']['size'] : null;
                    
                    $product_array['not_for_selling'] = 0;
                    $enable_stock = 1;
                    $product_array['enable_stock'] = $enable_stock;
                    $product_array['type'] = 'single';

                    $unit_name = "ML / FL / OZ";
                    $unit = Unit::where('business_id', $business_id)
                                        ->where('actual_name', $unit_name)->first();
                    if (! empty($unit)) {
                        $product_array['unit_id'] = $unit->id;
                    } else {
                        // $is_valid = false;
                        // $error_msg = "Unit with name not found. You can add unit from Products > Units";
                        // break;
                    }
                    $barcode_type = 'UPCA';
                    $product_array['barcode_type'] = $barcode_type;

                    // $tax_name = null;
                    // $product_array['tax'] = $tax_name;
                    // $tax_amount = 0;

                    //Add Tax
                    $tax = TaxRate::where('business_id', $business_id)
                                    ->where('name', "Product Tax")
                                    ->first();
                    if (! empty($tax)) {
                        $product_array['tax'] = $tax->id;
                        $tax_amount = $tax->amount;
                    } else {
                        $product_array['tax'] = null;
                        $tax_amount = 0;
                    }

                    $tax_type = 'exclusive';
                    $product_array['tax_type'] = $tax_type;
                    $product_array['alert_quantity'] = 5;
                    //Add brand
                    //Check if brand exists else create new
                    $brand_name = trim($value['node']['brandName']);
                    if (! empty($brand_name)) {
                        $brand = Brands::firstOrCreate(
                            ['business_id' => $business_id, 'name' => $brand_name],
                            ['created_by' => $user_id]
                        );
                        $product_array['brand_id'] = $brand->id;
                    }

                    //Add Category
                    //Check if category exists else create new
                    if (isset($value['node']['category']['name']) && ! empty(trim($value['node']['category']['name']))) {
                        $category = Category::firstOrCreate(
                            ['business_id' => $business_id, 'name' => trim($value['node']['category']['name']), 'category_type' => 'product','bvCatId' => $value['node']['category']['id']],
                            ['created_by' => $user_id, 'parent_id' => 0]
                        );
                        $product_array['category_id'] = $category->id;
                    }
                    else
                    {
                        $product_array['category_id'] = null;
                    }
                    $product_array['sku'] = isset($value['node']['sku']) && !empty($value['node']['sku']) ? trim($value['node']['sku']) : 'empty';

                    $product_array['weight'] = '';

                    if ($product_array['type'] == 'single') {
                        //Calculate profit margin
                        $profit_margin = '';
                        if (empty($profit_margin)) {
                            $profit_margin = $default_profit_percent;
                        }
                        $product_array['variation']['profit_percent'] = $profit_margin;

                        //Calculate purchase price
                        $dpp_inc_tax = 0;
                        $dpp_exc_tax = trim($value['node']['unitCost']);
                        if ($dpp_inc_tax == '' && $dpp_exc_tax == '') {
                            // $is_valid = false;
                            // $error_msg = "PURCHASE PRICE is required!!";
                            // break;
                            $dpp_inc_tax = 0;
                            $dpp_exc_tax = 0;
                        } else {
                            if($tax_amount == 0)
                            {
                                $dpp_inc_tax = ($dpp_inc_tax != '') ? number_format($dpp_inc_tax / 100, 2, '.', '') : 0;
                            }
                            $dpp_exc_tax = ($dpp_exc_tax != '') ? number_format($dpp_exc_tax / 100, 2, '.', '') : 0;
                        }
                        //Calculate Selling price
                        $selling_price = ! empty(trim($value['node']['unitPrice'])) ? number_format(trim($value['node']['unitPrice']) / 100, 2, '.', ''): 0;

                        //Calculate product prices
                        $product_prices = $this->calculateVariationPrices($dpp_exc_tax, $dpp_inc_tax, $selling_price, $tax_amount, $tax_type, $profit_margin);

                        //Assign Values
                        $product_array['variation']['dpp_inc_tax'] = $product_prices['dpp_inc_tax'];
                        $product_array['variation']['dpp_exc_tax'] = $product_prices['dpp_exc_tax'];
                        $product_array['variation']['dsp_inc_tax'] = $product_prices['dsp_inc_tax'];
                        $product_array['variation']['dsp_exc_tax'] = $product_prices['dsp_exc_tax'];

                        //Opening stock
                        if ($enable_stock == 1) 
                        {
                            if(isset($value['node']['quantities']['edges']) && !empty($value['node']['quantities']['edges']))
                            {
                                foreach($value['node']['quantities']['edges'] as $key_qty=>$val_qty)
                                {
                                    if(isset($val_qty['node']['location']['name']) && !empty($val_qty['node']['location']['name']))
                                    {
                                        $product_array['opening_stock_details']['quantity'] = $val_qty['node']['quantity'];
                                        $location_name = $val_qty['node']['location']['name'];
                                        $location = BusinessLocation::where('name', $location_name)
                                                                ->where('business_id', $business_id)
                                                                ->first();
                                        if (! empty($location)) {
                                            $product_array['opening_stock_details']['location_id'] = $location->id;
                                        } else {
                                            $location = BusinessLocation::where('business_id', $business_id)->first();
                                            $product_array['opening_stock_details']['location_id'] = $location->id;
                                        }
                                        $product_array['opening_stock_details']['expiry_date'] = null;
                                    }
                                }
                            }
                        }
                    }
                    $formated_data[] = $product_array;
                }
                
                // if (! $is_valid) {
                //     throw new \Exception($error_msg);
                // }

                // echo "<pre>";
                // print_r($formated_data);
                // echo "</pre>";
                // exit;

                // Adding Products to POS from Boulevard
                if (! empty($formated_data)) {
                    $imported_products = 0;
                    foreach ($formated_data as $index => $product_data) {
                        
                        //check if product is exist or not
                        $product = Product::where('business_id', $business_id)
                                    ->where('name', $product_data['name'])
                                    ->where('bvpId', $product_data['bvpId'])
                                    ->first();

                        if(empty($product))
                        {
                            $variation_data = $product_data['variation'];
                            unset($product_data['variation']);

                            $opening_stock = null;
                            if (! empty($product_data['opening_stock_details'])) {
                                $opening_stock = $product_data['opening_stock_details'];
                            }
                            if (isset($product_data['opening_stock_details'])) {
                                unset($product_data['opening_stock_details']);
                            }

                            //Create new product
                            $product = Product::create($product_data);
                            // //If auto generate sku generate new sku
                            // if ($product->sku == ' ') {
                            //     $sku = $this->productUtil->generateProductSku($product->id);
                            //     $product->sku = $sku;
                            //     $product->save();
                            // }

                            //Create single product variation
                            if ($product->type == 'single') {
                                $this->productUtil->createSingleProductVariation(
                                    $product,
                                    $product->sku,
                                    $variation_data['dpp_exc_tax'],
                                    $variation_data['dpp_inc_tax'],
                                    $variation_data['profit_percent'],
                                    $variation_data['dsp_exc_tax'],
                                    $variation_data['dsp_inc_tax']
                                );
                                if (! empty($opening_stock)) {
                                    $this->addOpeningStock($opening_stock, $product, $business_id);
                                }
                            }
                            $imported_products++;
                        }
                    }
                }

            }
            $output = ['success' => 0,
                'msg' => "Total Products Count from Boulevard Portal: " . count($formated_data) . " ||  Total " . $imported_products . " Products imported successfully!!",
            ];

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];

            return redirect('import-boulevard-products')->with('notification', $output);
        }

        return redirect('import-boulevard-products')->with('notification', $output);
    }
    private function fetchProducts($endCursor = null) {
        $api_token = $this->authHeader;
        $api_base_url = $this->api_base_blvd_url;
        $query = '
        query($after: String) {
            products(first: 100, after: $after) {
                edges
                {
                    node{
                        active
                        barcode
                        brandName
                        category{
                                id
                                name
                                retail
                        }
                        categoryId
                        color
                        createdAt
                        description
                        externalId
                        id
                        name
                        quantities(first:100)
                            {
                                edges
                                {
                                    node{
                                            location{
                                                id
                                                name
                                            }
                                            locationId
                                            quantity
                                    }
                                }
                                pageInfo{
                                    endCursor
                                    hasNextPage
                                }
                        }
                        quantityTrackingEnabled
                        size
                        sku
                        taxable
                        unitCost
                        unitPrice
                        updatedAt
                    }
                }
                pageInfo{
                    endCursor
                    hasNextPage
                }
            }
        }';
    
        $variables = ['after' => $endCursor];
    
        $data = json_encode([
            'query' => $query,
            'variables' => $variables
        ]);
    
        // Initialize cURL session
        $ch = curl_init();
    
        // Set the cURL options
        curl_setopt($ch, CURLOPT_URL, $api_base_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $api_token,
            'Content-Type: application/json',
            'Accept: application/json',
            // 'Content-Length: ' . strlen($data) // Try removing this line
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output for debugging
    
        // Execute the cURL request
        $response = curl_exec($ch);
    
        // Check for cURL errors
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            die('Curl error: ' . $error);
        }
    
        // Get the HTTP status code
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
        // Get the redirect URL if any
        $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    
        // Close the cURL session
        curl_close($ch);
    
        // Handle the response
        if ($http_status == 200) {
            // Decode the JSON response
            $response_data = json_decode($response, true);
    
            // Check for errors in the GraphQL response
            if (isset($response_data['errors'])) {
                $error_msg_notify =  'GraphQL errors: ' . json_encode($response_data['errors'], JSON_PRETTY_PRINT);

                $output = ['success' => 0,
                                'msg' => $error_msg_notify,
                            ];
                return $output;
            } else {
                $output = ['success' => 1,
                                'msg' => $response_data,
                            ];
                return $output;
            }
        } else {
            $error_msg_notify = 'Failed to retrieve products: HTTP Status ' . $http_status . "    ||   ".'Response: ' . $response;
            // if (!empty($redirect_url)) {
            //     return 'Redirect URL: ' . $redirect_url . "\n";
            // }
            //return 'Response: ' . $response . "\n";

            $output = ['success' => 0,
                                'msg' => $error_msg_notify,
                            ];
            return $output;
        }
    }

    private function calculateVariationPrices($dpp_exc_tax, $dpp_inc_tax, $selling_price, $tax_amount, $tax_type, $margin)
    {

        //Calculate purchase prices
        if ($dpp_inc_tax == 0) {
            $dpp_inc_tax = $this->productUtil->calc_percentage(
                $dpp_exc_tax,
                $tax_amount,
                $dpp_exc_tax
            );
        }

        if ($dpp_exc_tax == 0) {
            $dpp_exc_tax = $this->productUtil->calc_percentage_base($dpp_inc_tax, $tax_amount);
        }

        if ($selling_price != 0) {
            if ($tax_type == 'inclusive') {
                $dsp_inc_tax = $selling_price;
                $dsp_exc_tax = $this->productUtil->calc_percentage_base(
                    $dsp_inc_tax,
                    $tax_amount
                );
            } elseif ($tax_type == 'exclusive') {
                $dsp_exc_tax = $selling_price;
                $dsp_inc_tax = $this->productUtil->calc_percentage(
                    $selling_price,
                    $tax_amount,
                    $selling_price
                );
            }
        } else {
            $dsp_exc_tax = $this->productUtil->calc_percentage(
                $dpp_exc_tax,
                $margin,
                $dpp_exc_tax
            );
            $dsp_inc_tax = $this->productUtil->calc_percentage(
                $dsp_exc_tax,
                $tax_amount,
                $dsp_exc_tax
            );
        }

        return [
            'dpp_exc_tax' => $this->productUtil->num_f($dpp_exc_tax),
            'dpp_inc_tax' => $this->productUtil->num_f($dpp_inc_tax),
            'dsp_exc_tax' => $this->productUtil->num_f($dsp_exc_tax),
            'dsp_inc_tax' => $this->productUtil->num_f($dsp_inc_tax),
        ];
    }

     /**
     * Adds opening stock of a single product
     *
     * @param  array  $opening_stock
     * @param  obj  $product
     * @param  int  $business_id
     * @return void
     */
    private function addOpeningStock($opening_stock, $product, $business_id)
    {
        $user_id = request()->session()->get('user.id');

        $variation = Variation::where('product_id', $product->id)
            ->first();

        $total_before_tax = $opening_stock['quantity'] * $variation->dpp_inc_tax;

        $transaction_date = request()->session()->get('financial_year.start');
        $transaction_date = \Carbon::createFromFormat('Y-m-d', $transaction_date)->toDateTimeString();
        //Add opening stock transaction
        $transaction = Transaction::create(
            [
                'type' => 'opening_stock',
                'opening_stock_product_id' => $product->id,
                'status' => 'received',
                'business_id' => $business_id,
                'transaction_date' => $transaction_date,
                'total_before_tax' => $total_before_tax,
                'location_id' => $opening_stock['location_id'],
                'final_total' => $total_before_tax,
                'payment_status' => 'paid',
                'created_by' => $user_id,
            ]
        );
        //Get product tax
        $tax_percent = ! empty($product->product_tax->amount) ? $product->product_tax->amount : 0;
        $tax_id = ! empty($product->product_tax->id) ? $product->product_tax->id : null;

        $item_tax = $this->productUtil->calc_percentage($variation->default_purchase_price, $tax_percent);

        //Create purchase line
        $transaction->purchase_lines()->create([
            'product_id' => $product->id,
            'variation_id' => $variation->id,
            'quantity' => $opening_stock['quantity'],
            'item_tax' => $item_tax,
            'tax_id' => $tax_id,
            'pp_without_discount' => $variation->default_purchase_price,
            'purchase_price' => $variation->default_purchase_price,
            'purchase_price_inc_tax' => $variation->dpp_inc_tax,
            'exp_date' => ! empty($opening_stock['exp_date']) ? $opening_stock['exp_date'] : null,
        ]);
        //Update variation location details
        $this->productUtil->updateProductQuantity($opening_stock['location_id'], $product->id, $variation->id, $opening_stock['quantity']);

        //Add product location
        $this->__addProductLocation($product, $opening_stock['location_id']);
    }

    private function __addProductLocation($product, $location_id)
    {
        $count = DB::table('product_locations')->where('product_id', $product->id)
                                            ->where('location_id', $location_id)
                                            ->count();
        if ($count == 0) {
            DB::table('product_locations')->insert(['product_id' => $product->id,
                'location_id' => $location_id, ]);
        }
    }

    // Import Services as products 
    public function indexServices()
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }
        
        return view('import_products.index_services_boulevard');
    }
    public function importServices(Request $request)
    {
        if (! auth()->user()->can('product.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {

            //Set maximum php execution time
            ini_set('max_execution_time', 0);
            ini_set('memory_limit', -1);

            $start_process = $request->input('start_process');
            if ($start_process == "start") {
                // Fetch the initial page
                $response_from_func = $this->fetchServices();
                if($response_from_func['success'] == 0)
                {
                    return redirect('import-boulevard-services')->with('notification', $response_from_func);
                }
                else
                {
                    $response = $response_from_func['msg'];
                }
                $products = $response['data']['services']['edges'];
                $pageInfo = $response['data']['services']['pageInfo'];

                while ($pageInfo['hasNextPage']) {
                    $endCursor = $pageInfo['endCursor'];
                    $response_from_func = $this->fetchServices($endCursor);
                    if($response_from_func['success'] == 0)
                    {
                        return redirect('import-boulevard-services')->with('notification', $response_from_func);
                    }
                    else
                    {
                        $response = $response_from_func['msg'];
                    }
                    $products = array_merge($products, $response['data']['services']['edges']);
                    $pageInfo = $response['data']['services']['pageInfo'];
                }
            
                $business_id = $request->session()->get('user.business_id');
                $user_id = $request->session()->get('user.id');
                $default_profit_percent = $request->session()->get('business.default_profit_percent');
                $business_locations = BusinessLocation::where('business_id', $business_id)->get();
                $formated_data = [];

                $is_valid = true;
                $error_msg = '';

                $total_rows = count($products);
                
                DB::beginTransaction();
                // echo $total_rows."<br>";
                // echo "<pre>";
                // print_r($products);
                // echo "</pre>";
                // exit;

                foreach ($products as $key => $value) {

                    $row_no = $key + 1;
                    $product_array = [];
                    $product_array['business_id'] = $business_id;
                    $product_array['created_by'] = $user_id;
                    $product_array['bvpId'] = trim($value['node']['id']);
                    $product_array['ptype'] = 'service';
                    $product_array['duration'] = trim($value['node']['defaultDuration']);

                    $product_name = trim($value['node']['name']);
                    if (! empty($product_name)) {
                        $product_array['name'] = $product_name;
                    } else {
                        // $is_valid = false;
                        // $error_msg = "Product name is required in row no. $row_no";
                        // break;
                    }
                    $product_array['product_description'] = isset($value['node']['description']) ? $value['node']['description'] : null;
                    $product_array['barcode'] = isset($value['node']['barcode']) ? $value['node']['barcode'] : null;
                    $product_array['color'] = isset($value['node']['color']) ? $value['node']['color'] : null;
                    $product_array['size'] = isset($value['node']['size']) ? $value['node']['size'] : null;
                    
                    $product_array['not_for_selling'] = 0;
                    $enable_stock = 1;
                    $product_array['enable_stock'] = $enable_stock;
                    $product_array['type'] = 'single';

                    $unit_name = "ML / FL / OZ";
                    $unit = Unit::where('business_id', $business_id)
                                        ->where('actual_name', $unit_name)->first();
                    if (! empty($unit)) {
                        $product_array['unit_id'] = $unit->id;
                    } else {
                        // $is_valid = false;
                        // $error_msg = "Unit with name not found. You can add unit from Products > Units";
                        // break;
                    }
                    $barcode_type = 'UPCA';
                    $product_array['barcode_type'] = $barcode_type;

                    $tax_name = null;
                    $product_array['tax'] = $tax_name;
                    $tax_amount = 0;

                    //Add Tax
                    // $tax = TaxRate::where('business_id', $business_id)
                    //                 ->where('name', "Product Tax")
                    //                 ->first();
                    // if (! empty($tax)) {
                    //     $product_array['tax'] = $tax->id;
                    //     $tax_amount = $tax->amount;
                    // } else {
                        // $product_array['tax'] = null;
                        // $tax_amount = 0;
                    //}

                    $tax_type = 'exclusive';
                    $product_array['tax_type'] = $tax_type;
                    $product_array['alert_quantity'] = 5;
                    //Add brand
                    //Check if brand exists else create new
                    // $brand_name = trim($value['node']['brandName']);
                    // if (! empty($brand_name)) {
                    //     $brand = Brands::firstOrCreate(
                    //         ['business_id' => $business_id, 'name' => $brand_name],
                    //         ['created_by' => $user_id]
                    //     );
                    //     $product_array['brand_id'] = $brand->id;
                    // }
                    $product_array['brand_id'] = null;
                    //Add Category
                    //Check if category exists else create new
                    if (isset($value['node']['category']['name']) && ! empty(trim($value['node']['category']['name']))) {
                        $category = Category::firstOrCreate(
                            ['business_id' => $business_id, 'name' => trim($value['node']['category']['name']), 'category_type' => 'product','ctype' => 'service','bvCatId' => $value['node']['category']['id']],
                            ['created_by' => $user_id, 'parent_id' => 0]
                        );
                        $product_array['category_id'] = $category->id;
                    }
                    else
                    {
                        $product_array['category_id'] = null;
                    }
                    $product_array['sku'] = isset($value['node']['sku']) && !empty($value['node']['sku']) ? trim($value['node']['sku']) : 'empty';

                    $product_array['weight'] = '';

                    if ($product_array['type'] == 'single') {
                        //Calculate profit margin
                        $profit_margin = '';
                        if (empty($profit_margin)) {
                            $profit_margin = $default_profit_percent;
                        }
                        $product_array['variation']['profit_percent'] = 0;

                        //Calculate purchase price
                        $dpp_inc_tax = 0;
                        $dpp_exc_tax = trim($value['node']['defaultPrice']);
                        if ($dpp_inc_tax == '' && $dpp_exc_tax == '') {
                            // $is_valid = false;
                            // $error_msg = "PURCHASE PRICE is required!!";
                            // break;
                            $dpp_inc_tax = 0;
                            $dpp_exc_tax = 0;
                        } else {
                            if($tax_amount == 0)
                            {
                                $dpp_inc_tax = ($dpp_inc_tax != '') ? number_format($dpp_inc_tax / 100, 2, '.', '') : 0;
                            }
                            $dpp_exc_tax = ($dpp_exc_tax != '') ? number_format($dpp_exc_tax / 100, 2, '.', '') : 0;
                        }
                        //Calculate Selling price
                        $selling_price = ! empty(trim($value['node']['defaultPrice'])) ? number_format(trim($value['node']['defaultPrice']) / 100, 2, '.', ''): 0;

                        //Calculate product prices
                        $product_prices = $this->calculateVariationPrices($dpp_exc_tax, $dpp_inc_tax, $selling_price, $tax_amount, $tax_type, $profit_margin);

                        //Assign Values
                        $product_array['variation']['dpp_inc_tax'] = $product_prices['dpp_inc_tax'];
                        $product_array['variation']['dpp_exc_tax'] = $product_prices['dpp_exc_tax'];
                        $product_array['variation']['dsp_inc_tax'] = $product_prices['dsp_inc_tax'];
                        $product_array['variation']['dsp_exc_tax'] = $product_prices['dsp_exc_tax'];

                        //Opening stock
                        if ($enable_stock == 1) 
                        {
                            // if(isset($value['node']['quantities']['edges']) && !empty($value['node']['quantities']['edges']))
                            // {
                            //     foreach($value['node']['quantities']['edges'] as $key_qty=>$val_qty)
                            //     {
                            //         if(isset($val_qty['node']['location']['name']) && !empty($val_qty['node']['location']['name']))
                            //         {
                                        $product_array['opening_stock_details']['quantity'] = 100;
                                        //$location_name = $val_qty['node']['location']['name'];
                                        // $location = BusinessLocation::where('name', $location_name)
                                        //                         ->where('business_id', $business_id)
                                        //                         ->first();
                                        // if (! empty($location)) {
                                        //     $product_array['opening_stock_details']['location_id'] = $location->id;
                                        // } else {
                                            $location = BusinessLocation::where('business_id', $business_id)->first();
                                            $product_array['opening_stock_details']['location_id'] = $location->id;
                                        //}
                                        $product_array['opening_stock_details']['expiry_date'] = null;
                            //         }
                            //     }
                            // }
                        }
                    }
                    $formated_data[] = $product_array;
                }
                
                // if (! $is_valid) {
                //     throw new \Exception($error_msg);
                // }

                // echo "<pre>";
                // print_r($formated_data);
                // echo "</pre>";
                // exit;

                // Adding Products to POS from Boulevard
                if (! empty($formated_data)) {
                    $imported_products = 0;
                    foreach ($formated_data as $index => $product_data) {
                        
                        //check if product is exist or not
                        $product = Product::where('business_id', $business_id)
                                    ->where('name', $product_data['name'])
                                    ->where('bvpId', $product_data['bvpId'])
                                    ->first();

                        if(empty($product))
                        {
                            $variation_data = $product_data['variation'];
                            unset($product_data['variation']);

                            $opening_stock = null;
                            if (! empty($product_data['opening_stock_details'])) {
                                $opening_stock = $product_data['opening_stock_details'];
                            }
                            if (isset($product_data['opening_stock_details'])) {
                                unset($product_data['opening_stock_details']);
                            }

                            //Create new product
                            $product = Product::create($product_data);
                            // //If auto generate sku generate new sku
                            // if ($product->sku == ' ') {
                            //     $sku = $this->productUtil->generateProductSku($product->id);
                            //     $product->sku = $sku;
                            //     $product->save();
                            // }

                            //Create single product variation
                            if ($product->type == 'single') {
                                $this->productUtil->createSingleProductVariation(
                                    $product,
                                    $product->sku,
                                    $variation_data['dpp_exc_tax'],
                                    $variation_data['dpp_inc_tax'],
                                    $variation_data['profit_percent'],
                                    $variation_data['dsp_exc_tax'],
                                    $variation_data['dsp_inc_tax']
                                );
                                if (! empty($opening_stock)) {
                                    $this->addOpeningStock($opening_stock, $product, $business_id);
                                }
                            }
                            $imported_products++;
                        }
                    }
                }

            }
            $output = ['success' => 0,
                'msg' => "Total Services Count from Boulevard Portal: " . count($formated_data) . " ||  Total " . $imported_products . " Products imported successfully!!",
            ];

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];

            return redirect('import-boulevard-services')->with('notification', $output);
        }

        return redirect('import-boulevard-services')->with('notification', $output);
    }

    private function fetchServices($endCursor = null) {
        $api_token = $this->authHeader;
        $api_base_url = $this->api_base_blvd_url;
        $query = '
        query($after: String) {
            services(first: 100, after: $after) {
                edges
                {
                    node{
                        category{
                            id
                            name
                        }
                        categoryId
                        defaultDuration
                        defaultPrice
                        description
                        id
                        name
                        serviceOptionGroups{
                            id
                            name
                            serviceOptions{
                                id
                                name
                            }
                        }
                    }
                }
                pageInfo{
                    endCursor
                    hasNextPage
                }
            }
        }';
    
        $variables = ['after' => $endCursor];
    
        $data = json_encode([
            'query' => $query,
            'variables' => $variables
        ]);
    
        // Initialize cURL session
        $ch = curl_init();
    
        // Set the cURL options
        curl_setopt($ch, CURLOPT_URL, $api_base_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $api_token,
            'Content-Type: application/json',
            'Accept: application/json',
            // 'Content-Length: ' . strlen($data) // Try removing this line
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output for debugging
    
        // Execute the cURL request
        $response = curl_exec($ch);
    
        // Check for cURL errors
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            die('Curl error: ' . $error);
        }
    
        // Get the HTTP status code
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
        // Get the redirect URL if any
        $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    
        // Close the cURL session
        curl_close($ch);
    
        // Handle the response
        if ($http_status == 200) {
            // Decode the JSON response
            $response_data = json_decode($response, true);
    
            // Check for errors in the GraphQL response
            if (isset($response_data['errors'])) {
                $error_msg_notify =  'GraphQL errors: ' . json_encode($response_data['errors'], JSON_PRETTY_PRINT);

                $output = ['success' => 0,
                                'msg' => $error_msg_notify,
                            ];
                return $output;
            } else {
                $output = ['success' => 1,
                                'msg' => $response_data,
                            ];
                return $output;
            }
        } else {
            $error_msg_notify = 'Failed to retrieve services: HTTP Status ' . $http_status . "    ||   ".'Response: ' . $response;
            // if (!empty($redirect_url)) {
            //     return 'Redirect URL: ' . $redirect_url . "\n";
            // }
            //return 'Response: ' . $response . "\n";

            $output = ['success' => 0,
                                'msg' => $error_msg_notify,
                            ];
            return $output;
        }
    }
}
