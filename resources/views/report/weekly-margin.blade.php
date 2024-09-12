@extends('layouts.app')
@section('title', __('report.weekly-margin'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('report.weekly-margin')}}</h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">
            @component('components.filters', ['title' => __('report.filters')])
                <div class="col-md-3">
                    <div class="form-group">
                        <?php
                        $years = array_combine(range(date('Y'), 2000), range(date('Y'), 2000));
                        $months = [
                            '01' => __('report.jan'), '02' => __('report.feb'), '03' => __('report.mar'),
                            '04' => __('report.apr'), '05' => __('report.may'), '06' => __('report.jun'),
                            '07' => __('report.jul'), '08' => __('report.aug'), '09' => __('report.sep'),
                            '10' => __('report.oct'), '11' => __('report.nov'), '12' => __('report.dec')
                        ];
                        $currentYear = date('Y');
                        $currentMonth = date('m');
                        ?>
                        {!! Form::label('year_filter', __('report.select_a_year') . ':') !!}
                        {!! Form::select('year', $years, $year ?? $currentYear, ['class' => 'form-control', 'id' => 'year_filter']) !!}
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="form-group">
                        {!! Form::label('month_filter', __('report.select_a_month') . ':') !!}
                        {!! Form::select('month', $months, $month ?? $currentMonth, ['class' => 'form-control', 'id' => 'month_filter']) !!}
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
                        <table class="table table-bordered table-striped" id="weeklyMarginReportTable">
                            <thead>
                            <tr>
                                <th></th> <!-- Empty header for row labels -->
                                @foreach($currentPageWeeks as $weekStartDate)
                                    <th>{{ $weekStartDate }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody id="dynamicBody">
                                <tr>
                                    <td><b>@lang('lang_v1.service_revenue')</b></td>
                                    @foreach($currentPageServiceRevenue as $s_revenue)
                                        <td><span class="display_currency" data-currency_symbol="true">{{ $s_revenue }}</span></td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td><b>@lang('lang_v1.product_revenue')</b></td>
                                    @foreach($currentPageProductRevenue as $p_revenue)
                                        <td><span class="display_currency" data-currency_symbol="true">{{ $p_revenue }}</span></td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td><b>@lang('lang_v1.total_revenue')</b></td>
                                    @foreach($currentPageRevenue as $revenue)
                                        <td><span class="display_currency" data-currency_symbol="true">{{ $revenue }}</span></td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td><b>@lang('lang_v1.cogs')</b></td>
                                    @foreach($currentPageCogs as $cogs)
                                        <td><span class="display_currency" data-currency_symbol="true">{{ $cogs }}</span></td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td><b>@lang('lang_v1.backbar_expenses')</b></td>
                                    @foreach($currentPageWeeks as $weekStartDate)
                                        <td><span class="display_currency" data-currency_symbol="true">0.00</span></td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td><b>@lang('lang_v1.total_margin')</b></td>
                                    @foreach($currentPageTotalMargin as $total_margin)
                                        <td><span class="display_currency" data-currency_symbol="true">{{ $total_margin }}</span></td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td><b>@lang('lang_v1.total_margin_percentage')</b></td>
                                    @foreach($currentPageTotalMarginPercentage as $total_margin_percentage)
                                        <td>{{@num_format($total_margin_percentage)}} %</td>
                                    @endforeach
                                </tr>
                            </tbody>
                        </table>

                        <div class="pagination">
                            @if($pageNumber > 1)
                                <a href="{{ route('weekly-margin.report', ['page' => $pageNumber - 1, 'year' => $year, 'month' => $month]) }}">Previous Page</a>
                            @endif
                            @if($pageNumber < count($pages))
                                <a href="{{ route('weekly-margin.report', ['page' => $pageNumber + 1, 'year' => $year, 'month' => $month]) }}">Next Page</a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
    $(document).ready(function() {
        $('#weeklyMarginReportTable').DataTable({
            paging: false,
            searching: false,
            ordering: false,
            info: false
        });

        $('#year_filter, #month_filter').change(function() {
            var year = $('#year_filter').val();
            var month = $('#month_filter').val();
            window.location.href = "{{ route('weekly-margin.report') }}?year=" + year + "&month=" + month;
        });
        $('#collapseFilter').collapse('show'); // Assuming the filter's div has the ID 'filterPanel'
    });
</script>
@endsection
