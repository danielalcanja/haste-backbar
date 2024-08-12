@extends('layouts.app')
@section('title', __('product.import_boulevard_services'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('product.import_boulevard_services')
    </h1>
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
                {!! Form::open(['url' => action([\App\Http\Controllers\BoulevardImportDataController::class, 'importServices']), 'method' => 'post' ]) !!}
                    <div class="row">      
                        <div class="col-sm-4">
                            <input type="hidden" name="start_process" value="start">
                            <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white">@lang('product.import_services')</button>
                        </div>  
                    </div>
                {!! Form::close() !!}
            @endcomponent
        </div>
    </div>
</section>
<!-- /.content -->

@endsection