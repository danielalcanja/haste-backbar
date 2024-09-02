<div class="table-responsive">
<table class="table table-bordered table-striped" id="cmmsn_services_report" style="width: 100%;">
    <thead>
        <tr>
            <th>@lang('lang_v1.staff')</th>
            <th>@lang('lang_v1.date')</th>
            <th>@lang('lang_v1.client')</th>
            <th>@lang('lang_v1.service')</th>
            <th>@lang('lang_v1.list_price')</th>
            <th>@lang('lang_v1.quantity')</th>
            <th>@lang('lang_v1.item_discount')</th>
            <th>@lang('lang_v1.subtotal')</th>
            <th>@lang('lang_v1.rate')</th>
            <th>@lang('lang_v1.commission')</th>
        </tr>
    </thead>
    <tfoot>
        <tr class="bg-gray font-17 footer-total text-center">
            <td colspan="4"><strong>@lang('sale.total'):</strong></td>
            <td class="text-left"><span class="display_currency" id="cmmsn_footer_list_price_total" data-currency_symbol ="true"></span></td>
            <td class="text-left"><span id="cmmsn_footer_qty_total"></span></td>
            <td class="text-left"><span class="display_currency" id="cmmsn_footer_discount_total" data-currency_symbol ="true"></span></td>
            <td class="text-left"><span class="display_currency" id="cmmsn_footer_subtotal" data-currency_symbol ="true"></span></td>
            <td></td>
            <td class="text-left"><span class="display_currency" id="cmmsn_footer_commission_total" data-currency_symbol ="true"></span></td>
        </tr>
    </tfoot>
</table>
</div>