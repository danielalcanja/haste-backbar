<div class="table-responsive">
<table class="table table-bordered table-striped" id="cmmsn_summary_report" style="width: 100%;">
    <thead>
        <tr>
            <th>@lang('lang_v1.staff')</th>
            <th>@lang('lang_v1.commission_services')</th>
            <th>@lang('lang_v1.commission_products')</th>
            <th>@lang('sale.total')</th>
        </tr>
    </thead>
    <tfoot>
        <tr class="bg-gray font-17 footer-total text-center">
            <td><strong>@lang('sale.total'):</strong></td>
            <td class="text-left"><span class="display_currency" id="cmmsn_footer_services_total" data-currency_symbol ="true"></span></td>
            <td class="text-left"><span class="display_currency" id="cmmsn_footer_products_total" data-currency_symbol ="true"></span></td>
            <td class="text-left"><span class="display_currency" id="cmmsn_footer_total" data-currency_symbol ="true"></span></td>
        </tr>
    </tfoot>
</table>
</div>