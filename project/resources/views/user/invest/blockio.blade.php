@extends('layouts.user')

@push('css')
    
@endpush

@section('contents')
<div class="breadcrumb-area">
    <h3 class="title">@lang('BLOCK.IO')</h3>
    <ul class="breadcrumb">
        <li>
            <a href="#">@lang('Invest')</a>
        </li>
        <li>
            @lang('BLOCK.IO Invest')
        </li>
    </ul>
</div>


<div class="dashboard--content-item">
    <div class="row">
        <div class="col-lg-12">
            <div class="order-details-box">
                <div class="header">
                    <h4 class="title">
                        Block.io ({{ Session::get('coin') }}) Invest Information
                    </h4>
                </div>
                <div class="row justify-content-between px-4 py-5">
                    <div class="col-lg-12">
                        <div class="content">
                            <div class="panel-body text-center verify-success">
                                <img src="{{ Session::get('qrcode_url') }}">
                                <br>
                                <br>
                                <h3 class="text-center">Address: {{ Session::get('address') }}</h3>
                                <p>Please send approximately <b>{{ Session::get('amount') }}</b> {{ Session::get('coin') }} to this address. After completing your payment, <b>{{ Session::get('currency_sign') }}{{ Session::get('currency_value') }}</b> invest will be deposited. <br>This Process may take some time for confirmations. Thank you.</p>
                                
                                <h4><a href="javascript:history.back();">Go Back</a></h4>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('js')

@endpush