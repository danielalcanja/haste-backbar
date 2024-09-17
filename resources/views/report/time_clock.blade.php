@extends('layouts.app')
@section('title', __('report.time_clock'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('report.time_clock')}}</h1>
</section>

<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-md-12">
            @component('components.filters', ['title' => __('report.filters')])
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('time_clock_id',  __('report.user') . ':') !!}
                        {!! Form::select('time_clock_id', $users, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('report.all_users')]); !!}
                    </div>
                </div>
                <div class="col-md-4" style="display:none;">
                    <div class="form-group">
                        {!! Form::label('time_clock_business_id',  __('business.business_location') . ':') !!}
                        {!! Form::select('time_clock_business_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%']); !!}
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">

                        {!! Form::label('time_clock_date_filter', __('report.date_range') . ':') !!}
                        {!! Form::text('date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'time_clock_date_filter', 'readonly']); !!}
                    </div>
                </div>
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="box">
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="time_clock_report" style="width: 100%;">
                            <thead>
                                <tr>
                                <th>@lang( 'lang_v1.date' )</th>
                                <th>@lang('essentials::lang.employee')</th>
                                <th>@lang('lang_v1.hourly_rate')</th>
                                <th>@lang('essentials::lang.clock_in')</th>
                                <th>@lang('essentials::lang.clock_out')</th>
                                <th>@lang('essentials::lang.work_duration')</th>
                                <th>@lang('essentials::lang.total_hourly_payment')</th>
                                </tr>
                            </thead>
                            <tfoot>
                                <tr class="bg-gray font-17 footer-total text-center">
                                    <td colspan="5"><strong>@lang('sale.total'):</strong></td>
                                    <td class="text-left"><span id="time_clock_footer_total_duration"></span></td>
                                    <td class="text-left"><span class="display_currency" id="time_clock_footer_total_payment" data-currency_symbol ="true"></span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</section>
<!-- /.content -->
@endsection

@section('javascript')
    <script src="{{ asset('js/report.js?v=' . $asset_v) }}"></script>
@endsection