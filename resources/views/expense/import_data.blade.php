@extends('layouts.app')
@section('title', __('lang_v1.import_expense'))

@section('content')
<br/>
<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.import_expense')</h1>
</section>

<!-- Main content -->
<section class="content">
    
@if (session('notification') || !empty($notification))
    <div class="row">
        <div class="col-sm-12">
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
                @if(!empty($notification['msg']))
                    {{$notification['msg']}}
                @elseif(session('notification.msg'))
                    {{ session('notification.msg') }}
                @endif
              </div>
          </div>  
      </div>     
@endif
    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['class' => 'box-primary'])
                {!! Form::open(['url' => action([\App\Http\Controllers\ExpenseController::class, 'storeExpenseImportData']), 'method' => 'post', 'enctype' => 'multipart/form-data' ]) !!}
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="col-sm-12">
                                <div class="form-group">
                                    {!! Form::label('name', __( 'product.file_to_import' ) . ':') !!}
                                    {!! Form::file('expenses_csv', ['accept'=> '.xls', 'required' => 'required']); !!}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="col-sm-8">
                                <div class="form-group">
                                    {!! Form::label('transaction_date', __('messages.date') . ':*') !!}
                                    <div class="input-group">
                                        <span class="input-group-addon">
                                            <i class="fa fa-calendar"></i>
                                        </span>
                                        {!! Form::text('transaction_date', @format_datetime('now'), ['class' => 'form-control', 'required', 'id' => 'expense_transaction_date']); !!}
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-4">
                            <br>
                                <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('messages.submit')</button>
                            </div>
                        </div>
                    </div>
                {!! Form::close() !!}
                <br><br>
                <div class="row">
                    <div class="col-sm-4">
                        <a href="{{ asset('files/import_expense_csv_template.xls') }}" class="tw-dw-btn tw-dw-btn-success tw-text-white" download><i class="fa fa-download"></i> @lang('lang_v1.download_template_file')</a>
                    </div>
                </div>
                <br><br>
                <div class="row">
                    <div class="col-sm-12">
                        <strong>@lang('lang_v1.instruction_line1')</strong><br>@lang('lang_v1.instruction_line2')
                        <br><br>
                        <table class="table table-striped">
                            <tr>
                                <th>@lang('lang_v1.col_no')</th>
                                <th>@lang('lang_v1.col_name')</th>
                                <th>@lang('lang_v1.instruction')</th>
                            </tr>
                            <tr>
                                <td>1</td>
                                <td>@lang('business.location') <small class="text-muted">(@lang('lang_v1.optional')) <br>@lang('lang_v1.location_ins')</small></td>
                                <td>@lang('lang_v1.location_ins1')<br>
                                </td>
                            </tr>
                            <tr>
                                <td>7</td>
                                <td>@lang('lang_v1.expense_amount') <small class="text-muted">(@lang('lang_v1.required'))</small></td>
                                <td></td>
                            </tr>
                        </table>
                    </div>
                </div>
            @endcomponent
        </div>
    </div>
</section>
<!-- /.content -->

@endsection