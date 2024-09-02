@extends('layouts.app')
@section('title', __('report.commission'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('report.commission')}}</h1>
</section>

<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-md-12">
            @component('components.filters', ['title' => __('report.filters')])
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('cmmsn_id',  __('report.user') . ':') !!}
                        {!! Form::select('cmmsn_id', $users, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('report.all_users')]); !!}
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('cmmsn_business_id',  __('business.business_location') . ':') !!}
                        {!! Form::select('cmmsn_business_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%']); !!}
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">

                        {!! Form::label('cmmsn_date_filter', __('report.date_range') . ':') !!}
                        {!! Form::text('date_range', null, ['placeholder' => __('lang_v1.select_a_date_range'), 'class' => 'form-control', 'id' => 'cmmsn_date_filter', 'readonly']); !!}
                    </div>
                </div>
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <!-- Custom Tabs -->
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#commission_summary_tab" data-toggle="tab" aria-expanded="true"><i class="fa fa-cog" aria-hidden="true"></i> @lang('lang_v1.commission_summary')</a>
                    </li>

                    <li>
                        <a href="#commission_services_tab" data-toggle="tab" aria-expanded="true"><i class="fa fa-cog" aria-hidden="true"></i> @lang('lang_v1.commission_services')</a>
                    </li>

                    <li>
                        <a href="#commission_products_tab" data-toggle="tab" aria-expanded="true"><i class="fa fa-cog" aria-hidden="true"></i> @lang('lang_v1.commission_products')</a>
                    </li>

                </ul>

                <div class="tab-content">
                    <div class="tab-pane active" id="commission_summary_tab">
                        @include('report.partials.commission_summary')
                    </div>

                    <div class="tab-pane" id="commission_services_tab">
                        @include('report.partials.commission_services')
                    </div>

                    <div class="tab-pane" id="commission_products_tab">
                        @include('report.partials.commission_products')
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