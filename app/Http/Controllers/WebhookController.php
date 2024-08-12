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


class WebhookController extends Controller
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

    public function handle(Request $request)
    {
        // Get the raw JSON payload
        $payload = $request->getContent();
        $order_response = null;  // Initialize the order_response variable
        // $payload = '{
        //     "data": {
        //       "node": {
        //         "__typename": "Order",
        //         "id": "urn:blvd:Order:c9c0271f-98c8-4705-8148-5dfbdb57812a"
        //       }
        //     },
        //     "timestamp": "2024-08-01T13:32:53.768886Z",
        //     "resource": "Order13497",
        //     "event": "order.completed",
        //     "businessId": "urn:blvd:Business:038f0805-17be-4fc9-a313-97c62c527e2d",
        //     "apiApplicationId": "urn:blvd:ApiApplication:5e04c86f-da6f-468c-9e94-7d6bade1dd8b",
        //     "eventType": "ORDER_COMPLETED",
        //     "idempotencyKey": "01910e24-bac7-7d7e-8588-c97921c3819b",
        //     "webhookId": "urn:blvd:Webhook:d319ad9d-d855-415c-abfb-d02a1e4fa40f"
        //   }';

        // Decode the JSON payload
        $data = json_decode($payload, true);
        $order_response = null;
        //Check the event type
        if (isset($data['event'])) {
            switch (trim($data['event'])) {
                case 'order.completed':
                    Log::channel('webhook')->info('Webhook order.completed data paylod received', ['data' => $data]);
                    $order_response = $this->handleOrderCompleted($data);
                    break;
                case 'order_refund.closed':
                    Log::channel('webhook')->info('Webhook order_refund.closed data paylod received', ['data' => $data]);
                    break;
                case 'product_quantity_adjustment.created':
                    Log::channel('webhook')->info('Webhook product_quantity_adjustment.created data paylod received', ['data' => $data]);
                    break;
                // Add more cases as needed
                default:
                    Log::channel('webhook')->info('Unhandled event type', ['data' => $data]);
                    break;
            }
        }
        else
        {
            Log::channel('webhook')->info('Payload not found', ['data' => $data]);
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
                    Log::channel('webhook')->info("$error_msg_notify.'- OrderID :'.$orderId");
                    $order_response = [
                        'success' => 0,
                        'order_id' => $orderId,
                        'msg' => $error_msg_notify,
                    ];
                } else {
                    if(isset($response_data['data']['order']))
                    {
                        $order_response = $this->CreateOrUpdateOrder($response_data['data']['order']);
                    }
                }
            } else {
                $error_msg_notify = 'Failed to retrieve products: HTTP Status ' . $http_status . "    ||   ".'Response: ' . $response;
                $orderId = $webhookdata['data']['node']['id'] ?? null;
                Log::channel('webhook')->info("$error_msg_notify.'- OrderID :'.$orderId");
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
        echo "<pre>";
        print_r($blOrderData);
        echo "</pre>";
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
                        Log::channel('webhook')->info("OrderID :'.$orderIDfromBLv.' Successfully imported!!");
                    }
                } 
            }  
            else
            {
                Log::channel('webhook')->info("OrderID :'.$orderIDfromBLv.' User couldn't found");
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
            Log::channel('webhook')->info("Business Location could not found for OrderID :'.$orderIDfromBLv");
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
            Log::channel('webhook')->info($input['has_error']);
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
                    Log::channel('webhook')->info($mapPurchaseSellErr);
                    return $mapPurchaseSellErr;
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
        $productCardAmount = 0;
        foreach($order['lineGroups'] as $order_lines)
        {
            foreach($order_lines['lines'] as $product_line)
            {
                if(isset($product_line['productId']) && !empty($product_line['productId']))
                {
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
                        $accountCreditAmountSubtotal = number_format($product_line['currentSubtotal'] / 100, 2, '.', '');
                        $accountCreditAmount = $accountCreditAmount + $accountCreditAmountSubtotal;
                    }

                    if (substr($product_line['id'], 0, 2) === 'pc') 
                    {
                        $productCardAmountSubtotal = number_format($product_line['currentSubtotal'] / 100, 2, '.', '');
                        $productCardAmount = $productCardAmount + $productCardAmountSubtotal;
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
                    'mobile' => $customer_details['mobile'],
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
            'additional_expense_key_4' => 'ProductCardAmount',
            'additional_expense_value_4' => $productCardAmount,
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
                        else if($order_payments['paymentMeta']['label'] == 'Gift card')
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
        $new_sell_data['products'] = $product_lines;
        $new_sell_data['payment'] = $payment;
        return $new_sell_data;
    }
}


