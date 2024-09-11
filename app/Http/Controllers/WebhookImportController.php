<?php

namespace App\Http\Controllers;

use App\Http\Controllers\BoulevardImportDataController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

use App\User;
use App\Contact;
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
use Yajra\DataTables\Facades\DataTables;
use App\Events\ProductsCreatedOrModified;
use App\Utils\BusinessUtil;
use App\Utils\TransactionUtil;
use App\CmsnAgent;


class WebhookImportController extends Controller
{
   /**
     * All Utils instance.
     */
    protected $productUtil;

    protected $businessUtil;

    protected $transactionUtil;

    protected $moduleUtil;
    
    protected $BoulevardController;

    public function __construct(BoulevardImportDataController $BoulevardController, ProductUtil $productUtil,
    BusinessUtil $businessUtil,
    TransactionUtil $transactionUtil,
    ModuleUtil $moduleUtil)
    {
        $this->BoulevardController = $BoulevardController;
        $this->productUtil = $productUtil;
        $this->businessUtil = $businessUtil;
        $this->transactionUtil = $transactionUtil;
        $this->moduleUtil = $moduleUtil;
    }

    public function fetchOrders($endCursor = null, $qstring)
    {
        $api_token = $this->BoulevardController->authHeader;
        $api_base_url = $this->BoulevardController->api_base_blvd_url;

        $query = '
        query($after: String , $locationId: String , $qstring: String) {
            orders(first: 1000, after: $after,locationId:$locationId,query:$qstring) {
                edges {
                    node {
                        appliedVouchers{
                            id
                            voucher{
                                id
                                expiresOn
                                originatingOrder{
                                    id
                                }
                                product{
                                    id
                                    name
                                }
                                redeemedAt
                                services{
                                    id
                                    name
                                }
                            }
                        }
                        id
                        client{
                            id
                            dob
                            firstName
                            lastName
                            name
                            email
                            dob
                            notes{
                                id
                                text
                            }
                            mobilePhone
                            currentAccountBalance
                            appointmentCount
                            primaryLocation{
                                address{
                                    city
                                    country
                                    line1
                                    line2
                                    province
                                    state
                                    zip
                                }
                            }
                        }
                        closedAt
                        closedBy {
                            id
                            name
                            email
                            displayName
                            firstName
                            lastName
                            mobilePhone
                            role{
                                id
                                name
                            }
                            staffRoleId
                        }
                        createdAt
                        feeLines{
                            absoluteAmount
                            calculatedAmount
                            calculatedTaxAmount
                            id
                            label
                            percentageAmount
                        }
                        clientId
                        locationId
                        note
                        number
                        paymentGroups{
                            id
                            merchantId
                            merchantName
                            total
                            totalFees
                            totalPaid
                            totalUnpaid
                            payments{
                                id
                                merchantId
                                orderId
                                paidAmount
                                ... on OrderAccountCreditPayment {
                                        id
                                        paidAmount
                                        clientAccountBalance
                                        clientName
                                        merchantId
                                        orderId
                                        paymentMeta{
                                            label
                                        }
                                        refundAmount
                                }
                                ... on OrderCashPayment {
                                        id
                                        paidAmount
                                        merchantId
                                        orderId
                                        paymentMeta{
                                            label
                                        }
                                        refundAmount
                                }
                                ... on OrderCardPayment {
                                        id
                                        paidAmount
                                        merchantId
                                        orderId
                                        authEntryMethod
                                        cardAuthCode
                                        cardBrand
                                        cardExpMonth
                                        cardExpYear
                                        cardLast4
                                        paymentMeta{
                                            label
                                        }
                                        refundAmount
                                }
                                ... on OrderGiftCardPayment {
                                        id
                                        paidAmount
                                        merchantId
                                        orderId
                                        giftCardCode
                                        giftCardId
                                        paymentMeta{
                                            label
                                        }
                                }
                                ... on OrderOtherPayment {
                                        id
                                        paidAmount
                                        merchantId
                                        orderId
                                        note
                                        paymentMeta{
                                            label
                                        }
                                        refundAmount
                                }
                                ... on OrderProductCardPayment {
                                        id
                                        paidAmount
                                        merchantId
                                        orderId
                                        productCardBalance
                                        productCardId
                                        productId
                                        paymentMeta{
                                            label
                                        }
                                        refundAmount
                                }
                                ... on OrderVoucherPayment {
                                        id
                                        paidAmount
                                        merchantId
                                        orderId
                                        paymentMeta{
                                            label
                                        }
                                        refundAmount
                                }
                            }
                        }
                        summary{
                            currentDiscountAmount
                            currentFeeAmount
                            currentGratuityAmount
                            currentSubtotal
                            currentTaxAmount
                            currentTotal
                            initialDiscountAmount
                            initialFeeAmount
                            initialGratuityAmount
                            initialSubtotal
                            initialTaxAmount
                            initialTotal
                            refundAmount
                        }
                        lineGroups {
                            lines {
                                currentDiscountAmount
                                currentPrice
                                currentSubtotal
                                discounts{
                                    description
                                    discountAmount
                                    discountPercentage
                                    discountReason{
                                        id
                                        name
                                    }
                                    id
                                }
                                id
                                initialDiscountAmount
                                initialPrice
                                initialSubtotal
                                quantity
                                ... on OrderAccountCreditLine {
                                        id
                                        quantity
                                        currentDiscountAmount
                                        currentPrice
                                        currentSubtotal
                                        initialDiscountAmount
                                        initialPrice
                                        initialSubtotal
                                }
                                ... on OrderGiftCardLine {
                                        id
                                        quantity
                                        giftCardCode
                                        giftCardId
                                        currentDiscountAmount
                                        currentPrice
                                        currentSubtotal
                                        initialDiscountAmount
                                        initialPrice
                                        initialSubtotal
                                }
                                ... on OrderGratuityLine {
                                        id
                                        quantity
                                        currentDiscountAmount
                                        currentPrice
                                        currentSubtotal
                                        initialDiscountAmount
                                        initialPrice
                                        initialSubtotal
                                }
                                ... on OrderProductCardLine {
                                        id
                                        quantity
                                        currentDiscountAmount
                                        currentPrice
                                        currentSubtotal
                                        initialDiscountAmount
                                        initialPrice
                                        initialSubtotal
                                }
                                ... on OrderProductLine {
                                        id
                                        name
                                        productId
                                        quantity
                                        currentDiscountAmount
                                        currentPrice
                                        currentSubtotal
                                        initialDiscountAmount
                                        initialPrice
                                        initialSubtotal
                                        seller {
                                            id
                                            name
                                            email
                                            mobilePhone
                                            firstName
                                            lastName
                                        }
                                }
                                ... on OrderServiceLine {
                                        id
                                        name
                                        quantity
                                        serviceId
                                        currentDiscountAmount
                                        currentPrice
                                        currentSubtotal
                                        initialDiscountAmount
                                        initialPrice
                                        initialSubtotal
                                        initialStaffId
                                        unitListPrice
                                }
                            }
                        }
                        updatedAt
                        refunds{
                            closedAt
                            closedBy{
                                id
                                name
                            }
                            createdAt
                            id
                            number
                            orderId
                            payments{
                                id
                                orderPaymentId
                                refundAmount
                                refundLimit
                                ... on OrderRefundCashPayment {
                                        id
                                        orderPaymentId
                                        refundAmount
                                        refundLimit
                                }
                                ... on OrderRefundCardPayment {
                                        id
                                        orderPaymentId
                                        refundAmount
                                        refundLimit
                                }
                                ... on OrderRefundGiftCardPayment {
                                        giftCardCode
                                        id
                                        orderPaymentId
                                        refundAmount
                                        refundLimit
                                }
                            }
                            refundReasonText
                            status
                            summary{
                                refundAmount
                                refundTax
                                refundTotal
                            }
                        }
                    }
                }
                pageInfo {
                    endCursor
                    hasNextPage
                }
            }
        }';

        $variables = [
            'after' => $endCursor,
            'locationId' => 'urn:blvd:Location:e1c830f8-60fa-401b-a1a9-83f56abc254b',
            'qstring' => $qstring
        ];

        $data = json_encode([
            'query' => $query,
            'variables' => $variables
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_base_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $api_token,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            die('Curl error: ' . $error);
        }

        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_status == 200) {
            $response_data = json_decode($response, true);

            if (isset($response_data['errors'])) {
                return ['error' => 'GraphQL errors: ' . json_encode($response_data['errors'], JSON_PRETTY_PRINT)];
            } else {
                return $response_data;
            }
        } else {
            return ['error' => 'Failed to retrieve Orders: HTTP Status ' . $http_status];
        }
    }

    public function fetchAllOrders()
    {
        $date_ranges = [
            //"closedAt < '2024-04-01' AND closedAt >= '2024-03-01'"
        ];
        //Log::channel('webhookimport')->info("date_range :". json_encode($date_ranges,true));
        $all_orders = [];
        if(!empty($date_ranges))
        {
            foreach ($date_ranges as $qstring) {
                $endCursor = null;
                do {
                    $response = $this->fetchOrders($endCursor, $qstring);

                    if (isset($response['error'])) {
                        echo $response['error'] . "\n";
                        break;
                    }

                    $orders = $response['data']['orders']['edges'] ?? [];
                    // echo "<pre>";
                    // print_r($orders);
                    // echo "</pre>";
                    if (empty($orders)) {
                        echo "No orders found for date range: $qstring\n";
                        break; // Skip to the next date range if no orders are found
                    }

                    // Merge the orders into the main array
                    $all_orders = array_merge($all_orders, $orders);

                    $pageInfo = $response['data']['orders']['pageInfo'];
                    $endCursor = $pageInfo['endCursor'];
                } while ($pageInfo['hasNextPage']);
            }
        }

        return $all_orders;
    }
    public function handle(Request $request)
    {
        ini_set('max_execution_time', 0);
        ini_set('memory_limit', -1);
        
        $orders = $this->fetchAllOrders();
        echo 'Total Orders Count: ' . count($orders) . "<br>";

        // $order_sum = 0;
        // foreach($orders as $order)
        // {
        //     $order_sum = $order_sum + number_format($order['node']['summary']['currentTotal'] / 100, 2, '.', '');
        // }
        // echo $order_sum;
        // exit;
        $order_response = null;  // Initialize the order_response variable
        foreach($orders as $order)
        {
            if(isset($order['node']['id']) && !empty($order['node']['id']))
            {
                $orderImportId = $order['node']['id'];
                $order_response = null;
                $order_response = $this->CreateOrUpdateOrder($order['node']);
                // if(isset($order['node']['summary']) && $order['node']['summary']['currentTotal'] != 0 && $order['node']['summary']['refundAmount'] == 0)
                // {
                //     $order_response = $this->CreateOrUpdateOrder($order['node']);
                // }
                // else
                // {
                //     Log::channel('webhookimport')->info("Order skipped with OrderID :".$orderImportId."And OrderNo".$order['node']['number']);
                // }
            }
        }
        return response()->json(['message' => $order_response], 200);
    }

    protected function handleOrderCompleted($webhookdata)
    {
        $api_token = $this->BoulevardController->authHeader;
        $api_base_url = $this->BoulevardController->api_base_blvd_url;
        $query = '
        query($id: String) {
            order(id: $id) {
                appliedVouchers{
                    id
                    voucher{
                        id
                        expiresOn
                        originatingOrder{
                            id
                        }
                        product{
                            id
                            name
                        }
                        redeemedAt
                        services{
                            id
                            name
                        }
                    }
                }
                id
                client{
                    id
                    dob
                    firstName
                    lastName
                    name
                    email
                    dob
                    notes{
                        id
                        text
                    }
                    mobilePhone
                    currentAccountBalance
                    appointmentCount
                    primaryLocation{
                        address{
                            city
                            country
                            line1
                            line2
                            province
                            state
                            zip
                        }
                    }
                }
                closedAt
                closedBy {
                    id
                    name
                    email
                    displayName
                    firstName
                    lastName
                    mobilePhone
                    role{
                        id
                        name
                    }
                    staffRoleId
                }
                createdAt
                feeLines{
                    absoluteAmount
                    calculatedAmount
                    calculatedTaxAmount
                    id
                    label
                    percentageAmount
                }
                clientId
                locationId
                note
                number
                paymentGroups{
                    id
                    merchantId
                    merchantName
                    total
                    totalFees
                    totalPaid
                    totalUnpaid
                    payments{
                        id
                        merchantId
                        orderId
                        paidAmount
                        ... on OrderAccountCreditPayment {
                                id
                                paidAmount
                                clientAccountBalance
                                clientName
                                merchantId
                                orderId
                                paymentMeta{
                                    label
                                }
                                refundAmount
                        }
                        ... on OrderCashPayment {
                                id
                                paidAmount
                                merchantId
                                orderId
                                paymentMeta{
                                    label
                                }
                                refundAmount
                        }
                        ... on OrderCardPayment {
                                id
                                paidAmount
                                merchantId
                                orderId
                                authEntryMethod
                                cardAuthCode
                                cardBrand
                                cardExpMonth
                                cardExpYear
                                cardLast4
                                paymentMeta{
                                    label
                                }
                                refundAmount
                        }
                        ... on OrderGiftCardPayment {
                                id
                                paidAmount
                                merchantId
                                orderId
                                giftCardCode
                                giftCardId
                                paymentMeta{
                                    label
                                }
                        }
                        ... on OrderOtherPayment {
                                id
                                paidAmount
                                merchantId
                                orderId
                                note
                                paymentMeta{
                                    label
                                }
                                refundAmount
                        }
                        ... on OrderProductCardPayment {
                                id
                                paidAmount
                                merchantId
                                orderId
                                productCardBalance
                                productCardId
                                productId
                                paymentMeta{
                                    label
                                }
                                refundAmount
                        }
                        ... on OrderVoucherPayment {
                                id
                                paidAmount
                                merchantId
                                orderId
                                paymentMeta{
                                    label
                                }
                                refundAmount
                        }
                    }
                }
                summary{
                    currentDiscountAmount
                    currentFeeAmount
                    currentGratuityAmount
                    currentSubtotal
                    currentTaxAmount
                    currentTotal
                    initialDiscountAmount
                    initialFeeAmount
                    initialGratuityAmount
                    initialSubtotal
                    initialTaxAmount
                    initialTotal
                    refundAmount
                }
                lineGroups {
                    lines {
                        currentDiscountAmount
                        currentPrice
                        currentSubtotal
                        discounts{
                            description
                            discountAmount
                            discountPercentage
                            discountReason{
                                id
                                name
                            }
                            id
                        }
                        id
                        initialDiscountAmount
                        initialPrice
                        initialSubtotal
                        quantity
                        ... on OrderAccountCreditLine {
                                id
                                quantity
                                currentDiscountAmount
                                currentPrice
                                currentSubtotal
                                initialDiscountAmount
                                initialPrice
                                initialSubtotal
                        }
                        ... on OrderGiftCardLine {
                                id
                                quantity
                                giftCardCode
                                giftCardId
                                currentDiscountAmount
                                currentPrice
                                currentSubtotal
                                initialDiscountAmount
                                initialPrice
                                initialSubtotal
                        }
                        ... on OrderGratuityLine {
                                id
                                quantity
                                currentDiscountAmount
                                currentPrice
                                currentSubtotal
                                initialDiscountAmount
                                initialPrice
                                initialSubtotal
                        }
                        ... on OrderProductCardLine {
                                id
                                quantity
                                currentDiscountAmount
                                currentPrice
                                currentSubtotal
                                initialDiscountAmount
                                initialPrice
                                initialSubtotal
                        }
                        ... on OrderProductLine {
                                id
                                name
                                productId
                                quantity
                                currentDiscountAmount
                                currentPrice
                                currentSubtotal
                                initialDiscountAmount
                                initialPrice
                                initialSubtotal
                                seller {
                                    id
                                    name
                                    email
                                    mobilePhone
                                    firstName
                                    lastName
                                }
                        }
                        ... on OrderServiceLine {
                                id
                                name
                                quantity
                                serviceId
                                currentDiscountAmount
                                currentPrice
                                currentSubtotal
                                initialDiscountAmount
                                initialPrice
                                initialSubtotal
                                initialStaffId
                                unitListPrice
                        }
                    }
                }
                updatedAt
                refunds{
                    closedAt
                    closedBy{
                        id
                        name
                    }
                    createdAt
                    id
                    number
                    orderId
                    payments{
                        id
                        orderPaymentId
                        refundAmount
                        refundLimit
                        ... on OrderRefundCashPayment {
                                id
                                orderPaymentId
                                refundAmount
                                refundLimit
                        }
                        ... on OrderRefundCardPayment {
                                id
                                orderPaymentId
                                refundAmount
                                refundLimit
                        }
                        ... on OrderRefundGiftCardPayment {
                                giftCardCode
                                id
                                orderPaymentId
                                refundAmount
                                refundLimit
                        }
                    }
                    refundReasonText
                    status
                    summary{
                        refundAmount
                        refundTax
                        refundTotal
                    }
                }
            }
        }';
        if(isset($webhookdata['data']['node']['id']) && !empty($webhookdata['data']['node']['id']))
        {
            $variables = ['id' => $webhookdata['data']['node']['id']];
        
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
                    $orderId = $webhookdata['data']['node']['id'] ?? null;
                    Log::channel('webhookimport')->info("$error_msg_notify.'- OrderID :'.$orderId");
                    $order_response = [
                        'success' => 0,
                        'order_id' => $orderId,
                        'msg' => $error_msg_notify,
                    ];
                } else {
                    if(isset($response_data['data']['order']))
                    {
                        if(isset($response_data['data']['order']['summary']) && $response_data['data']['order']['summary']['currentTotal'] != 0 && $response_data['data']['order']['summary']['refundAmount'] == 0)
                        {
                            $order_response = $this->CreateOrUpdateOrder($response_data['data']['order']);
                        }
                        else
                        {
                            $order_response = null;
                        }
                    }
                }
            } else {
                $error_msg_notify = 'Failed to retrieve order: HTTP Status ' . $http_status . "    ||   ".'Response: ' . $response;
                $orderId = $webhookdata['data']['node']['id'] ?? null;
                Log::channel('webhookimport')->info("$error_msg_notify.'- OrderID :'.$orderId");
                $order_response = [
                    'success' => 0,
                    'order_id' => $orderId,
                    'msg' => $error_msg_notify,
                ];
            }
        }
        else
        {
            $order_response = [
                'success' => 0,
                'msg' => "No Payload found",
            ];
        }
        return $order_response;
    }

    public function CreateOrUpdateOrder($blOrderData)
    {
        // echo "<pre>";
        // print_r($blOrderData);
        // echo "</pre>";
        $order_response = null;  // Initialize the order_response variable
        $business_location = BusinessLocation::where('bvlId', $blOrderData['locationId'])->first();
        
        if(!empty($business_location))
        {
            $orderIDfromBLv = $blOrderData['id'];
            $business_id = $business_location->business_id;
            $business = Business::find($business_id);
            $location_id = $business_location->id;
            $business_data = [
                'id' => $business_id,
                'accounting_method' => $business->accounting_method,
                'location_id' => $location_id,
                'pos_settings' => json_decode($business->pos_settings, true),
                'business' => $business,
            ];

            $sell = Transaction::where('business_id', $business_id)
                                ->where('location_id', $location_id)
                                ->where('bvoId', $blOrderData['id'])
                                ->with('sell_lines', 'sell_lines.product', 'payment_lines')
                                ->first();
             
            if(isset($blOrderData['closedBy']['email']))
            {
                $user = User::where('email', $blOrderData['closedBy']['email'])->first();
            }
            else
            {
                $user = User::where('email', 'daniel@trio.dev')->first();
            }
            
            if(!empty($user))
            {
                $user_id = $user->id;
                if(empty($sell))
                {
                    $order_response = $this->createNewSaleFromBvOrder($business_id, $location_id ,$user_id, $blOrderData, $business_data); 
                    
                    if(!empty($order_response) && $order_response['success']==1)
                    {
                        Log::channel('webhookimport')->info("OrderNo:".$blOrderData['number']." And OrderID :'.$orderIDfromBLv.' Successfully imported!!");
                    }
                }
                else
                {
                    Log::channel('webhookimport')->info("Order exist with OrderID :".$orderIDfromBLv." And OrderNo: ".$blOrderData['number']);
                } 
            }  
            else
            {
                Log::channel('webhookimport')->info("OrderID :'.$orderIDfromBLv.' User couldn't found And OrderNo: ".$blOrderData['number']);
                $order_response = [
                    'success' => 0,
                    'order_id' => $orderIDfromBLv,
                    'invoice_no' => $blOrderData['number'],
                    'msg' => "User could not found",
                ];
            }
        }
        else
        {
            Log::channel('webhookimport')->info("Business Location could not found for OrderID :'.$orderIDfromBLv");
            $order_response = [
                'success' => 0,
                'order_id' => $orderIDfromBLv,
                'invoice_no' => $blOrderData['number'],
                'msg' => "Business Location could not found",
            ];
        }

        return $order_response;
        // $sell = Product::where('business_id', $business_id)
        //                             ->where('name', $product_data['name'])
        //                             ->where('bvpId', $product_data['bvpId'])
        //                             ->first();
    }

    public function createNewSaleFromBvOrder($business_id, $location_id ,$user_id, $order, $business_data)
    {
        //$input = $this->formatOrderToSale($business_id, $user_id, $order);
        $input = $this->formatBvOrderToSale($business_id,$location_id,$user_id,$order);

        if (! empty($input['has_error'])) {
            Log::channel('webhookimport')->info($input['has_error']);
            return $input['has_error'];
        }

        DB::beginTransaction();
            $invoice_total = [
                'total_before_tax' => number_format($order['summary']['currentSubtotal'] / 100, 2, '.', ''),
                'tax' => number_format($order['summary']['currentTaxAmount'] / 100, 2, '.', ''),
            ];

            // echo "<pre>";
            // print_r($invoice_total);
            // echo "</pre>";

            // echo "<pre>";
            // print_r($input);
            // echo "</pre>";
            
            $transaction = $this->transactionUtil->createSellTransaction($business_id, $input, $invoice_total, $user_id, false);
            $transaction->bvoId = $order['id'];
            //$transaction->commission_agent = $user_id;
            $transaction->save();

            //Create sell lines
            $this->transactionUtil->createOrUpdateSellLines($transaction, $input['products'], $location_id, false, null, [], false);

            $this->transactionUtil->createOrUpdatePaymentLines($transaction, $input['payment'], $business_id, $user_id, false);

            if ($input['status'] == 'final') {
                //update product stock
                foreach ($input['products'] as $product) {
                    if ($product['enable_stock']) {
                        $this->productUtil->decreaseProductQuantity(
                            $product['product_id'],
                            $product['variation_id'],
                            $input['location_id'],
                            $product['quantity']
                        );
                    }
                }

                //Update payment status
                $transaction->payment_status = 'paid';
                $transaction->save();

                try {
                    $this->transactionUtil->mapPurchaseSell($business_data, $transaction->sell_lines, 'purchase');
                } catch (PurchaseSellMismatch $e) {
                    DB::rollBack();
                    
                    $mapPurchaseSellErr = [
                        'success' => 0,
                        'error_type' => 'order_insuficient_product_qty',
                        'order_id' => $order['id'],
                        'invoice_no' => $order['number'],
                        'msg' => $e->getMessage(),
                    ];
                    Log::channel('webhookimport')->info($mapPurchaseSellErr);
                    return $mapPurchaseSellErr;
                }
            }

            $appointments = $this->getAppointments($order['id'],$order['closedAt'],$order['locationId']);
            if(!empty($appointments) && is_array($appointments))
            {
                if(isset($transaction->sell_lines) && !empty($transaction->sell_lines))
                {
                    foreach($transaction->sell_lines as $transaction_sell_line)
                    {
                        if(isset($transaction_sell_line->appointment_service_id) && !empty($transaction_sell_line->appointment_service_id) && empty($transaction_sell_line->product_seller_id))
                        {
                            if(!empty($appointments['appointmentServices']))
                            {
                                foreach($appointments['appointmentServices'] as $appointmentService)
                                {
                                    if($appointmentService['id'] == $transaction_sell_line->appointment_service_id)
                                    {
                                        if(isset($appointmentService['staff']['email']) && !empty($appointmentService['staff']['email']))
                                        {
                                            $staff = User::where('email',$appointmentService['staff']['email'])->first();
                                            if(!empty($staff))
                                            {
                                                if(isset($staff->cmmsn_percent) && $staff->cmmsn_percent > 0)
                                                {
                                                    CmsnAgent::create([
                                                        'transaction_id' => $transaction->id,
                                                        'transaction_sell_line_id' => $transaction_sell_line->id,
                                                        'discount_amount' => $transaction_sell_line->line_discount_amount_blvd,
                                                        'user_id' => $staff->id,
                                                        'cmmsn_percent' => $staff->cmmsn_percent,
                                                        'cmmsn_type' => 'service',
                                                    ]);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        } else if(isset($transaction_sell_line->product_seller_id) && !empty($transaction_sell_line->product_seller_id) && empty($transaction_sell_line->appointment_service_id))
                        {
                            $staffSeller = User::where('id',$transaction_sell_line->product_seller_id)->first();
                            if(!empty($staffSeller))
                            {
                                if(isset($staffSeller->product_cmmsn_percent) && $staffSeller->product_cmmsn_percent > 0)
                                {
                                    CmsnAgent::create([
                                        'transaction_id' => $transaction->id,
                                        'transaction_sell_line_id' => $transaction_sell_line->id,
                                        'discount_amount' => $transaction_sell_line->line_discount_amount_blvd,
                                        'user_id' => $staffSeller->id,
                                        'cmmsn_percent' => $staffSeller->product_cmmsn_percent,
                                        'cmmsn_type' => 'product',
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        DB::commit();
        return [
                'success' => 1,
                'order_id' => $order['id'],
                'invoice_no' => $order['number'],
                'msg' => "Order Created Successfully!!",
            ];
    }

    public function formatBvOrderToSale($business_id, $location_id, $user_id, $order, $sell = null)
    {
        //Create sell line data
        $product_lines = [];

        //For updating sell lines
        $sell_lines = [];
        if (! empty($sell)) {
            $sell_lines = $sell->sell_lines;
        }
        $accountCreditAmount = 0;
        $accountCreditDiscountAmount = 0;
        $productCardAmount = 0;
        $productCardDiscountAmount = 0;
        $giftCardAmount = 0;
        $giftCardDiscountAmount = 0;
        foreach($order['lineGroups'] as $order_lines)
        {
            foreach($order_lines['lines'] as $product_line)
            {
                if(isset($product_line['productId']) && !empty($product_line['productId']))
                {
                    $product_seller_id = null;
                    if(isset($product_line['seller']['email']) && !empty($product_line['seller']['email']))
                    {
                        $staffProductSeller = User::where('email',$product_line['seller']['email'])->first();
                        if(!empty($staffProductSeller))
                        {
                            $product_seller_id = $staffProductSeller->id;
                        }
                    }
                    $product = Product::where('business_id', $business_id)
                            ->where('bvpId', $product_line['productId'])
                            ->with(['variations'])
                            ->first();
                            
                    $unit_price = number_format($product_line['currentPrice'] / 100, 2, '.', '') / $product_line['quantity'];
                    $line_tax = 0;
                    $unit_line_tax = $line_tax / $product_line['quantity'];
                    $unit_price_inc_tax = $unit_price + $unit_line_tax;
                    if (! empty($product)) {

                        //Set sale line variation;If single product then first variation
                        if ($product->type == 'single') {
                            $variation = $product->variations->first();
                        } 
                        
                        if (empty($variation)) {
                            return ['has_error' => [
                                'succuss' => 0,
                                'error_type' => 'order_product_not_found',
                                'productId' => $product_line['productId'],
                                'product' => $product_line['name'],
                                'orderId' => $order['id'],
                                'orderNumber' => $order['number'],
                            ],
                            ];
                            exit;
                        }
        
                        //Check if line tax exists append to sale line data
                        $tax_id = null;
                        
                        /// out of stock and manage with stock
                        $current_stock = $this->productUtil->getCurrentStock($variation->id, $location_id);

                        if ($current_stock < $product_line['quantity']) {
                            
                            $product_info = Variation::where('variations.id', $variation->id)
                                ->join('products AS P', 'variations.product_id', '=', 'P.id')
                                ->leftjoin('tax_rates AS TR', 'P.tax', 'TR.id')
                                ->where('P.business_id', $business_id)
                                ->select(['P.id', 'variations.id as variation_id',
                                    'P.enable_stock', 'TR.amount as tax_percent',
                                    'TR.id as tax_id'])
                                ->first();

                            //Check for tra, location_id, opening_stock_product_id, type=opening stock.
                            $os_transaction = Transaction::where('business_id', $business_id)
                            ->where('location_id', $location_id)
                            ->where('type', 'opening_stock')
                            ->where('opening_stock_product_id', $product->id)
                            ->first();
                            $opening_stock = [];
                            $opening_stock = ['quantity' => trim($product_line['quantity']),
                                'location_id' => $location_id
                            ];

                            $this->addOpeningStockForBlvdData($opening_stock, $product_info, $business_id, $variation->default_purchase_price, $os_transaction,$user_id);
                        }

                        $product_data = [
                            'product_id' => $product->id,
                            'unit_price' => $unit_price,
                            'unit_price_inc_tax' => $unit_price_inc_tax,
                            'variation_id' => $variation->id,
                            'quantity' => $product_line['quantity'],
                            'enable_stock' => $product->enable_stock,
                            'item_tax' => $line_tax,
                            'tax_id' => $tax_id,
                            'line_item_id' => $product_line['productId'],
                            'line_discount_amount_blvd' => number_format($product_line['currentDiscountAmount'] / 100, 2, '.', ''),
                            'appointment_service_id' => null,
                            'product_seller_id' => $product_seller_id
                        ];
                        $product_lines[] = $product_data;
                    } else {
                        return ['has_error' => [
                            'success' => 0,
                            'error_type' => 'order_product_not_found',
                            'productId' => $product_line['productId'],
                            'product' => $product_line['name'],
                            'orderId' => $order['id'],
                            'orderNumber' => $order['number'],
                        ],
                        ];
                        exit;
                    }
                }
                if(isset($product_line['serviceId']) && !empty($product_line['serviceId']))
                {
                    $appointment_service_id = "";
                    if(isset($product_line['id']) && !empty($product_line['id']))
                    {
                        $appointment_service_id_data = explode('_',$product_line['id']);
                        if(is_array($appointment_service_id_data))
                        {
                            $appointment_service_id = "urn:blvd:AppointmentService:".$appointment_service_id_data[2];
                        }
                    }

                    $product = Product::where('business_id', $business_id)
                            ->where('bvpId', $product_line['serviceId'])
                            ->with(['variations'])
                            ->first();
                            
                    $unit_price = number_format($product_line['currentPrice'] / 100, 2, '.', '') / $product_line['quantity'];
                    $line_tax = 0;
                    $unit_line_tax = $line_tax / $product_line['quantity'];
                    $unit_price_inc_tax = $unit_price + $unit_line_tax;
                    if (! empty($product)) {

                        //Set sale line variation;If single product then first variation
                        if ($product->type == 'single') {
                            $variation = $product->variations->first();
                        } 
                        
                        if (empty($variation)) {
                            return ['has_error' => [
                                'succuss' => 0,
                                'error_type' => 'order_product_not_found',
                                'serviceId' => $product_line['serviceId'],
                                'product' => $product_line['name'],
                                'orderId' => $order['id'],
                                'orderNumber' => $order['number'],
                            ],
                            ];
                            exit;
                        }
        
                        //Check if line tax exists append to sale line data
                        $tax_id = null;

                        /// out of stock and manage with stock
                        $current_stock = $this->productUtil->getCurrentStock($variation->id, $location_id);

                        if ($current_stock < $product_line['quantity']) {
                            
                            $product_info = Variation::where('variations.id', $variation->id)
                                ->join('products AS P', 'variations.product_id', '=', 'P.id')
                                ->leftjoin('tax_rates AS TR', 'P.tax', 'TR.id')
                                ->where('P.business_id', $business_id)
                                ->select(['P.id', 'variations.id as variation_id',
                                    'P.enable_stock', 'TR.amount as tax_percent',
                                    'TR.id as tax_id'])
                                ->first();

                            //Check for tra, location_id, opening_stock_product_id, type=opening stock.
                            $os_transaction = Transaction::where('business_id', $business_id)
                            ->where('location_id', $location_id)
                            ->where('type', 'opening_stock')
                            ->where('opening_stock_product_id', $product->id)
                            ->first();
                            $opening_stock_service = [];
                            $opening_stock_service = ['quantity' => trim($product_line['quantity']),
                                'location_id' => $location_id
                            ];

                            $this->addOpeningStockForBlvdData($opening_stock_service, $product_info, $business_id, $variation->default_purchase_price, $os_transaction,$user_id);
                        }
        
                        $product_data = [
                            'product_id' => $product->id,
                            'unit_price' => $unit_price,
                            'unit_price_inc_tax' => $unit_price_inc_tax,
                            'variation_id' => $variation->id,
                            'quantity' => $product_line['quantity'],
                            'enable_stock' => $product->enable_stock,
                            'item_tax' => $line_tax,
                            'tax_id' => $tax_id,
                            'line_item_id' => $product_line['serviceId'],
                            'line_discount_amount_blvd' => number_format($product_line['currentDiscountAmount'] / 100, 2, '.', ''),
                            'appointment_service_id' => $appointment_service_id,
                            'product_seller_id' => null
                        ];
                        $product_lines[] = $product_data;
                    } else {
                        return ['has_error' => [
                            'success' => 0,
                            'error_type' => 'order_product_not_found',
                            'serviceId' => $product_line['serviceId'],
                            'product' => $product_line['name'],
                            'orderId' => $order['id'],
                            'orderNumber' => $order['number'],
                        ],
                        ];
                        exit;
                    }
                }
                if(isset($product_line['id']) && !empty($product_line['id']))
                {
                    if (substr($product_line['id'], 0, 2) === 'ac') 
                    {
                        $accountCreditAmountSubtotal = number_format($product_line['currentPrice'] / 100, 2, '.', '');
                        $accountCreditAmount = $accountCreditAmount + $accountCreditAmountSubtotal;
                        
                        $accountCreditDiscountAmountSubtotal = number_format($product_line['currentDiscountAmount'] / 100, 2, '.', '');
                        $accountCreditDiscountAmount = $accountCreditDiscountAmount + $accountCreditDiscountAmountSubtotal;
                    }

                    if (substr($product_line['id'], 0, 2) === 'pc') 
                    {
                        $productCardAmountSubtotal = number_format($product_line['currentPrice'] / 100, 2, '.', '');
                        $productCardAmount = $productCardAmount + $productCardAmountSubtotal;
                        
                        $productCardDiscountAmountSubtotal = number_format($product_line['currentDiscountAmount'] / 100, 2, '.', '');
                        $productCardDiscountAmount = $productCardDiscountAmount + $productCardDiscountAmountSubtotal;
                    }

                    if (substr($product_line['id'], 0, 2) === 'gc') 
                    {
                        $giftCardAmountSubtotal = number_format($product_line['currentPrice'] / 100, 2, '.', '');
                        $giftCardAmount = $giftCardAmount + $giftCardAmountSubtotal;

                        $giftCardDiscountAmountSubtotal = number_format($product_line['currentDiscountAmount'] / 100, 2, '.', '');
                        $giftCardDiscountAmount = $giftCardDiscountAmount + $giftCardDiscountAmountSubtotal;
                    }
                }
            }
        }
        
        //Get customer details
        if(isset($order['clientId']) && !empty($order['clientId']) && !empty($order['client']))
        {
            $order_customer_id = $order['clientId'];

            $customer_details = [];
            
            $customer_details = [
                'first_name' => $order['client']['firstName'],
                'last_name' => $order['client']['firstName'],
                'email' => ! empty($order['client']['email']) ? $order['client']['email'] : null,
                'name' => $order['client']['name'],
                'mobile' => $order['client']['mobilePhone'],
                'address_line_1' => ! empty($order['client']['primaryLocation']['address']['line1']) ? $order['client']['primaryLocation']['address']['line1'] : null,
                'address_line_2' => ! empty($order['client']['primaryLocation']['address']['line2']) ? $order['client']['primaryLocation']['address']['line2'] : null,
                'city' => ! empty($order['client']['primaryLocation']['address']['city']) ? $order['client']['primaryLocation']['address']['city'] : null,
                'state' => ! empty($order['client']['primaryLocation']['address']['state']) ? $order['client']['primaryLocation']['address']['state'] : null,
                'country' => ! empty($order['client']['primaryLocation']['address']['country']) ? $order['client']['primaryLocation']['address']['country'] : null,
                'zip_code' => ! empty($order['client']['primaryLocation']['address']['zip']) ? $order['client']['primaryLocation']['address']['zip'] : null,
            ];
            

            //if (! empty($customer_details['email'])) {
                $customer = Contact::where('business_id', $business_id)
                               // ->where('email', $customer_details['email'])
                                ->where('bvcId',$order['clientId'])
                                ->OnlyCustomers()
                                ->first();
            //}

            //If customer not found create new
            if (empty($customer)) {
                $ref_count = $this->transactionUtil->setAndGetReferenceCount('contacts', $business_id);
                $contact_id = $this->transactionUtil->generateReferenceNumber('contacts', $ref_count, $business_id);

                $customer_data = [
                    'business_id' => $business_id,
                    'type' => 'customer',
                    'first_name' => $customer_details['first_name'],
                    'last_name' => $customer_details['last_name'],
                    'name' => $customer_details['name'],
                    'email' => $customer_details['email'],
                    'contact_id' => $contact_id,
                    'mobile' => isset($customer_details['mobile']) && !empty($customer_details['mobile']) ? $customer_details['mobile'] : 'empty',
                    'city' => $customer_details['city'],
                    'state' => $customer_details['state'],
                    'country' => $customer_details['country'],
                    'created_by' => $user_id,
                    'address_line_1' => $customer_details['address_line_1'],
                    'address_line_2' => $customer_details['address_line_2'],
                    'zip_code' => $customer_details['zip_code'],
                    'bvcId' => $order['clientId'],
                ];

                //if name is blank make email address as name
                if (empty(trim($customer_data['name']))) {
                    $customer_data['first_name'] = $customer_details['email'];
                    $customer_data['name'] = $customer_details['email'];
                }
                $customer = Contact::create($customer_data);
            }
        }
        else
        {
            return ['has_error' => [
                'success' => 0,
                'error_type' => 'order_client_not_found',
                'orderNumber' => $order['number'],
                'orderId' => $order['id']
            ],
            ];
            exit;
        }
        
        //tax rate 
        $tax_rate_id = null;
        if(!empty($order['summary']['currentTaxAmount']))
        {
            $tax = TaxRate::where('business_id', $business_id)
                                        ->where('name', "Product Tax")
                                        ->first();
            if (! empty($tax)) {
                $tax_rate_id = $tax->id;
                
            } else {
                $tax_rate_id = null;
            }
        }
        $sell_status = "final";

        $new_sell_data = [
            'business_id' => $business_id,
            'location_id' => $location_id,
            'contact_id' => $customer->id,
            'discount_type' => 'fixed',
            'discount_amount' => number_format($order['summary']['currentDiscountAmount'] / 100, 2, '.', ''),
            'shipping_charges' => 0,
            'final_total' => number_format($order['summary']['currentTotal'] / 100, 2, '.', ''),
            'created_by' => $user_id,
            'status' => $sell_status == 'quotation' ? 'draft' : $sell_status,
            'is_quotation' => $sell_status == 'quotation' ? 1 : 0,
            'sub_status' => $sell_status == 'quotation' ? 'quotation' : null,
            'payment_status' => 'paid',
            'additional_notes' => null,
            'transaction_date' => \Carbon::parse($order['closedAt'])->format('Y-m-d H:i:s'),
            'customer_group_id' => $customer->customer_group_id,
            'tax_rate_id' => $tax_rate_id,
            'sale_note' => $order['note'],
            'commission_agent' => null,
            'is_direct_sale' => 1,
            'invoice_no' => $order['number'],
            'order_addresses' => null,
            'shipping_charges' => 0,
            'shipping_details' => null,
            'shipping_status' => null,
            'shipping_address' => null,
            'additional_expense_key_1' => 'GratuityAmount',
            'additional_expense_value_1' => number_format($order['summary']['currentGratuityAmount'] / 100, 2, '.', ''),
            'additional_expense_key_2' => 'FeeAmount',
            'additional_expense_value_2' => number_format($order['summary']['currentFeeAmount'] / 100, 2, '.', ''),
            'additional_expense_key_3' => 'AccountCreditAmount',
            'additional_expense_value_3' => $accountCreditAmount,
            'ac_discount_amount' => $accountCreditDiscountAmount,
            'additional_expense_key_4' => 'ProductCardAmount',
            'additional_expense_value_4' => $productCardAmount,
            'pc_discount_amount' => $productCardDiscountAmount,
            'additional_expense_key_5' => 'GiftCardAmount',
            'additional_expense_value_5' => $giftCardAmount,
            'gc_discount_amount' => $giftCardDiscountAmount,
        ];
        $payment = [];
        if(!empty($order['paymentGroups'])) 
        {
            foreach($order['paymentGroups'] as $order_payment_group)
            {
                if(!empty($order_payment_group['payments'])) 
                {
                    foreach($order_payment_group['payments'] as $order_payments)
                    {
                        if(isset($order_payments['refundAmount']))
                        {
                            $refundAmount =  $this->transactionUtil->num_uf($order_payments['refundAmount']);
                        }
                        else
                        {
                            $refundAmount = 0;
                        }

                        if($refundAmount <= 0)
                        {
                            if(isset($order_payments['cardExpYear']) && !empty($order_payments['cardExpYear']))
                            {
                                $payment[]= [
                                    'amount' => number_format($order_payments['paidAmount'] / 100, 2, '.', ''),
                                    'method' => 'card',
                                    'card_transaction_number' => !empty($order_payments['cardBrand']) ? $order_payments['cardBrand'] : '',
                                    'card_number' => !empty($order_payments['cardLast4']) ? $order_payments['cardLast4'] : '',
                                    'card_type' => '',
                                    'card_holder_name' => '',
                                    'card_month' => !empty($order_payments['cardExpMonth']) ? $order_payments['cardExpMonth'] : '',
                                    'card_year' => !empty($order_payments['cardExpYear']) ? $order_payments['cardExpYear'] : '',
                                    'card_security' => '',
                                    'cheque_number' => '',
                                    'bank_account_number' => '',
                                    'note' => '',
                                    'paid_on' => \Carbon::parse($order['closedAt'])->format('Y-m-d H:i:s'),
                                ];
                            }
                            else if($order_payments['paymentMeta']['label'] == 'Cash')
                            {
                                $payment[]= [
                                    'amount' => number_format($order_payments['paidAmount'] / 100, 2, '.', ''),
                                    'method' => 'cash',
                                    'card_transaction_number' => '',
                                    'card_number' => '',
                                    'card_type' => '',
                                    'card_holder_name' => '',
                                    'card_month' => '',
                                    'card_year' => '',
                                    'card_security' => '',
                                    'cheque_number' => '',
                                    'bank_account_number' => '',
                                    'note' => '',
                                    'paid_on' => \Carbon::parse($order['closedAt'])->format('Y-m-d H:i:s'),
                                ];
                            }
                            else if($order_payments['paymentMeta']['label'] == 'Clover')
                            {
                                $payment[]= [
                                    'amount' => number_format($order_payments['paidAmount'] / 100, 2, '.', ''),
                                    'method' => 'custom_pay_1',
                                    'transaction_no_1' => '',
                                    'card_transaction_number' => '',
                                    'card_number' => '',
                                    'card_type' => '',
                                    'card_holder_name' => '',
                                    'card_month' => '',
                                    'card_year' => '',
                                    'card_security' => '',
                                    'cheque_number' => '',
                                    'bank_account_number' => '',
                                    'note' => '',
                                    'paid_on' => \Carbon::parse($order['closedAt'])->format('Y-m-d H:i:s'),
                                ];
                            }
                            else if($order_payments['paymentMeta']['label'] == 'Venmo/Zelle')
                            {
                                $payment[]= [
                                    'amount' => number_format($order_payments['paidAmount'] / 100, 2, '.', ''),
                                    'method' => 'custom_pay_2',
                                    'transaction_no_2' => '',
                                    'card_transaction_number' => '',
                                    'card_number' => '',
                                    'card_type' => '',
                                    'card_holder_name' => '',
                                    'card_month' => '',
                                    'card_year' => '',
                                    'card_security' => '',
                                    'cheque_number' => '',
                                    'bank_account_number' => '',
                                    'note' => '',
                                    'paid_on' => \Carbon::parse($order['closedAt'])->format('Y-m-d H:i:s'),
                                ];
                            }
                            else if($order_payments['paymentMeta']['label'] == 'Voucher')
                            {
                                $payment[]= [
                                    'amount' => number_format($order_payments['paidAmount'] / 100, 2, '.', ''),
                                    'method' => 'custom_pay_3',
                                    'transaction_no_3' => '',
                                    'card_transaction_number' => '',
                                    'card_number' => '',
                                    'card_type' => '',
                                    'card_holder_name' => '',
                                    'card_month' => '',
                                    'card_year' => '',
                                    'card_security' => '',
                                    'cheque_number' => '',
                                    'bank_account_number' => '',
                                    'note' => '',
                                    'paid_on' => \Carbon::parse($order['closedAt'])->format('Y-m-d H:i:s'),
                                ];
                            }
                            else if($order_payments['paymentMeta']['label'] == 'Gift Card')
                            {
                                $payment[]= [
                                    'amount' => number_format($order_payments['paidAmount'] / 100, 2, '.', ''),
                                    'method' => 'custom_pay_4',
                                    'card_transaction_number' => '',
                                    'card_number' => '',
                                    'card_type' => '',
                                    'card_holder_name' => '',
                                    'card_month' => '',
                                    'card_year' => '',
                                    'card_security' => '',
                                    'cheque_number' => '',
                                    'bank_account_number' => '',
                                    'note' => '',
                                    'paid_on' => \Carbon::parse($order['closedAt'])->format('Y-m-d H:i:s'),
                                ];
                            }
                            else if($order_payments['paymentMeta']['label'] == 'Account Credit')
                            {
                                $payment[]= [
                                    'amount' => number_format($order_payments['paidAmount'] / 100, 2, '.', ''),
                                    'method' => 'custom_pay_5',
                                    'card_transaction_number' => '',
                                    'card_number' => '',
                                    'card_type' => '',
                                    'card_holder_name' => '',
                                    'card_month' => '',
                                    'card_year' => '',
                                    'card_security' => '',
                                    'cheque_number' => '',
                                    'bank_account_number' => '',
                                    'note' => '',
                                    'paid_on' => \Carbon::parse($order['closedAt'])->format('Y-m-d H:i:s'),
                                ];
                            }
                            else
                            {
                                if(isset($order_payments['paymentMeta']['label']) && !empty($order_payments['paymentMeta']['label']))
                                {
                                    $label = "Paid via ".$order_payments['paymentMeta']['label'];
                                }
                                else
                                {
                                    $label = "";
                                }
                                $payment[]= [
                                    'amount' => number_format($order_payments['paidAmount'] / 100, 2, '.', ''),
                                    'method' => 'other',
                                    'card_transaction_number' => '',
                                    'card_number' => '',
                                    'card_type' => '',
                                    'card_holder_name' => '',
                                    'card_month' => '',
                                    'card_year' => '',
                                    'card_security' => '',
                                    'cheque_number' => '',
                                    'bank_account_number' => '',
                                    'note' => $label,
                                    'paid_on' => \Carbon::parse($order['closedAt'])->format('Y-m-d H:i:s'),
                                ];
                            }
                        }
                    }
                }

            }
        }
        $new_sell_data['products'] = $product_lines;
        $new_sell_data['payment'] = $payment;
        return $new_sell_data;
    }

    public function getAppointments($orderId = "", $closedAt = "",$locationId = "")
    {
        if(!empty($orderId) && !empty($closedAt))
        {
            $filterStartDate = \Carbon::parse($closedAt);
            $filterEndDate = $filterStartDate->copy()->addDay()->format('Y-m-d');
            $filterStartDate = $filterStartDate->format('Y-m-d');
            $filterDateString = "startAt <= '$filterEndDate' AND startAt > '$filterStartDate'";
            
            $response = $this->fetchAppointments(null , $filterDateString,$locationId);
            $appointments = $response['data']['appointments']['edges'];
            $pageInfo = $response['data']['appointments']['pageInfo'];

            while ($pageInfo['hasNextPage']) {
                $endCursor = $pageInfo['endCursor'];
                $response = $this->fetchAppointments($endCursor , $filterDateString,$locationId);
                $appointments = array_merge($appointments, $response['data']['appointments']['edges']);
                $pageInfo = $response['data']['appointments']['pageInfo'];
            }

            $filteredAppointments = array_filter($appointments, function($appointment) use ($orderId) {
                return $appointment['node']['orderId'] === $orderId;
            });

            $filteredServices = array_map(function($appointment) {
                return $appointment['node'];
            }, $filteredAppointments);
            
            $filteredServices = array_values($filteredServices); // Re-index the array

            if (isset($filteredServices[0])) {
                $filteredServices = $filteredServices[0]; // Flatten to the first element's root array
            } else {
                $filteredServices = []; // Set to an empty array if no elements exist
            }

            return $filteredServices;
        }
    }

    protected function fetchAppointments($endCursor = null, $filterDateString = "", $locationId = "") {
        $api_token = $this->BoulevardController->authHeader;
        $api_base_url = $this->BoulevardController->api_base_blvd_url;
        $query = '
        query($after: String , $locationId: String , $qstring: String) {
            appointments(first: 100, after: $after,locationId:$locationId,query:$qstring) {
                edges
                {
                    node {
                        id
                        notes
                        locationId
                        clientId
                        orderId
                        startAt
                        endAt
                        appointmentServices {
                            id
                            price
                            service {
                                id
                                name
                            }
                            staff {
                                id
                                name
                                email
                                firstName
                                lastName
                                mobilePhone
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
    
        $variables = ['after' => $endCursor,"locationId"=>$locationId,'qstring' => $filterDateString];
    
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
                return 'GraphQL errors: ' . json_encode($response_data['errors'], JSON_PRETTY_PRINT) . "\n";
            } else {
                return $response_data;
            }
        } else {
            return 'Failed to retrieve appointments: HTTP Status ' . $http_status . "\n";
            if (!empty($redirect_url)) {
                return 'Redirect URL: ' . $redirect_url . "\n";
            }
            return 'Response: ' . $response . "\n";
        }
    }

    /**
     * Adds opening stock of a single product
     *
     * @param  array  $opening_stock
     * @param  obj  $product
     * @param  int  $business_id
     * @return void
     */
    private function addOpeningStockForBlvdData($opening_stock, $product, $business_id, $unit_cost_before_tax, $transaction = null,$user_id)
    {
        $transaction_date = request()->session()->get('financial_year.start');
        $transaction_date = \Carbon::createFromFormat('Y-m-d', $transaction_date)->toDateTimeString();

        //Get product tax
        $tax_percent = ! empty($product->tax_percent) ? $product->tax_percent : 0;
        $tax_id = ! empty($product->tax_id) ? $product->tax_id : null;

        $item_tax = $this->productUtil->calc_percentage($unit_cost_before_tax, $tax_percent);

        //total before transaction tax
        $total_before_trans_tax = $opening_stock['quantity'] * ($unit_cost_before_tax + $item_tax);

        //Add opening stock transaction
        if (empty($transaction)) {
            $transaction = new Transaction();
            $transaction->type = 'opening_stock';
            $transaction->status = 'received';
            $transaction->opening_stock_product_id = $product->id;
            $transaction->business_id = $business_id;
            $transaction->transaction_date = $transaction_date;
            $transaction->location_id = $opening_stock['location_id'];
            $transaction->payment_status = 'paid';
            $transaction->created_by = $user_id;
            $transaction->total_before_tax = 0;
            $transaction->final_total = 0;
        }
        $transaction->total_before_tax += $total_before_trans_tax;
        $transaction->final_total += $total_before_trans_tax;
        $transaction->save();

        //Create purchase line
        $transaction->purchase_lines()->create([
            'product_id' => $product->id,
            'variation_id' => $product->variation_id,
            'quantity' => $opening_stock['quantity'],
            'pp_without_discount' => $unit_cost_before_tax,
            'item_tax' => $item_tax,
            'tax_id' => $tax_id,
            'pp_without_discount' => $unit_cost_before_tax,
            'purchase_price' => $unit_cost_before_tax,
            'purchase_price_inc_tax' => $unit_cost_before_tax + $item_tax
        ]);
        //Update variation location details
        $this->productUtil->updateProductQuantity($opening_stock['location_id'], $product->id, $product->variation_id, $opening_stock['quantity']);
    }
}


