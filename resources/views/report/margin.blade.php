@extends('layouts.app')
@section('title', __('report.margin'))

@section('content')

<!-- Content Header (Page header) -->
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">{{ __('report.margin')}}</h1>
</section>

<!-- Main content -->
<section class="content">
    <div class="row">
        <div class="col-md-12">
            @component('components.filters', ['title' => __('report.filters')])
                <!-- <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('cmmsn_id',  __('report.user') . ':') !!}
                        {!! Form::select('cmmsn_id', $users, null, ['class' => 'form-control select2', 'style' => 'width:100%', 'placeholder' => __('report.all_users')]); !!}
                    </div>
                </div> -->
                <!-- <div class="col-md-4">
                    <div class="form-group">
                        {!! Form::label('margin_business_id',  __('business.business_location') . ':') !!}
                        {!! Form::select('margin_business_id', $business_locations, null, ['class' => 'form-control select2', 'style' => 'width:100%']); !!}
                    </div>
                </div> -->

                <div class="col-md-3">
                    <div class="form-group">
                        <?php
                        // Generate an array of years from 2020 to the current year
                        $years = array_combine(range(date('Y'), 2000), range(date('Y'), 2000));
                        $currentYear = date('Y');
                        ?>
                        {!! Form::label('year_filter', __('report.select_a_year') . ':') !!}
                        {!! Form::select('year', $years, $currentYear, ['class' => 'form-control', 'id' => 'year_filter']); !!}
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
                        <table class="table table-bordered table-striped" id="margin_report" style="width: 100%;">
                            <thead>
                                <tr id="dynamicHeader">
                                    <!-- Headers will be inserted here dynamically -->
                                </tr>
                            </thead>
                            <tbody id="dynamicBody">
                                <!-- Dynamic rows will be appended here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</section>
<!-- /.content -->
@endsection

<!-- <script src="{{ asset('js/report.js?v=' . $asset_v) }}"></script> -->
@section('javascript')
<script type="text/javascript">
$(document).ready(function() {
    updateMarginReport();
    // $('select#margin_business_id').change(function() {
    //     updateMarginReport();
    // });
    $('select#year_filter').change(function() {
        $('#margin_report').DataTable().destroy();
        updateMarginReport();
    });
});

function updateMarginReport()
{
    $.ajax({
        url: "/reports/margin-report",
        data: {
            year: $('select#year_filter').val()
        },
        success: function(jsonResponse) {
            try {
                const json = JSON.parse(jsonResponse);
                console.log("Parsed JSON Response:", json);

                // Check if columns and data are defined and are arrays
                if (Array.isArray(json.columns) && Array.isArray(json.data)) {
                    var dynamicHeader = $('#dynamicHeader');
                    dynamicHeader.empty(); // Clear any existing headers

                    dynamicHeader.append('<th>&nbsp;</th>');
                    // Populate table headers
                    json.columns.forEach(function(column) {
                        dynamicHeader.append('<th>' + column.title + '</th>');
                    });

                    // Populate table rows
                    var dynamicBody = $('#dynamicBody');
                    dynamicBody.empty(); // Clear any existing rows

                    // Create Revenue and COGS rows
                    var revenueRow = '<tr><td><strong>'+LANG.revenue+'</strong></td>';
                    var cogsRow = '<tr><td><strong>'+LANG.cogs+'</strong></td>';
                    var gmRow = '<tr><td><strong>'+LANG.gm+'</strong></td>';
                    var gmpRow = '<tr><td><strong>'+LANG.gmp+'</strong></td>';
                    var oetRow = '<tr><td><strong>'+LANG.oes+'</strong></td>';
                    var oepRow = '<tr><td><strong>'+LANG.oep+'</strong></td>';

                    json.data.forEach(function(rowData) {
                        json.columns.forEach(function(column) {
                            if (rowData[column.data]) 
                            { 
                                const values = rowData[column.data].split('\n');
                                const revenue = parseFloat(values[0].split(': ')[1]);
                                const cogs = parseFloat(values[1].split(': ')[1]);
                                const oe = parseFloat(values[2].split(': ')[1]);
                                const ex_categories = values[3].split(': ')[1];

                                const gm = revenue - cogs; 
                                const rev_cogs = revenue + cogs;
                                const gmp = (gm*100) / rev_cogs; 
                                const roundedGmp = gmp !== undefined && !isNaN(gmp) ? gmp.toFixed(2) : 0.00;
                                
                                const oet = oe - cogs; 
                                const oep = (oet*100) / oe;
                                const roundedOep = oep !== undefined && !isNaN(oep) ? oep.toFixed(2) : 0.00;

                                revenueRow += '<td>' + (revenue !== undefined ? __currency_trans_from_en(revenue,true) : '') + '</td>';
                                cogsRow += '<td>' + (cogs !== undefined ? __currency_trans_from_en(cogs,true) : '') + '</td>';
                                gmRow += '<td>' + (gm !== undefined ? __currency_trans_from_en(gm,true) : '') + '</td>';
                                gmpRow += '<td>' + roundedGmp + '%' + '</td>';
                                oetRow += '<td>';
                                oetRow += (oet !== undefined ? __currency_trans_from_en(oet,true) : '');
                                if(ex_categories != "cat_empty" && oet != null && oet != undefined && !isNaN(oet) && oet !== 0)
                                {
                                    // Add collapsible ex_categories
                                    const collapseId = 'collapse-' + column.data;
                                    const categoryList = `<ul class="list-group list-group-unbordered text-center">${ex_categories.split(',').map(cat => `<li class="list-group-item">${cat}</li>`).join('')}</ul>`;
                                    oetRow += `<span class="no-print">
                                                <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#${collapseId}" aria-expanded="false" aria-controls="${collapseId}">
                                                    Expense Categories
                                                </button>
                                                <div class="collapse" id="${collapseId}">
                                                    <div class="card card-body">
                                                        ${categoryList}
                                                    </div>
                                                </div>
                                            </span>`;
                                }
                                oetRow += '</td>';
                                oepRow += '<td>' + roundedOep + '%' + '</td>';

                                
                            } else {
                                // Handle null or undefined data
                                revenueRow += '<td>'+__currency_trans_from_en(0.00,true)+'</td>';
                                cogsRow += '<td>'+__currency_trans_from_en(0.00,true)+'</td>';
                                gmRow += '<td>'+__currency_trans_from_en(0.00,true)+'</td>';
                                gmpRow += '<td>0%</td>';
                                oetRow += '<td>'+__currency_trans_from_en(0.00,true)+'</td>';
                                oepRow += '<td>0%</td>';
                            }
                        });
                    });

                    revenueRow += '</tr>';
                    cogsRow += '</tr>';
                    gmRow += '</tr>';
                    gmpRow += '</tr>';
                    oetRow += '</tr>';
                    oepRow += '</tr>';

                    // Append rows to the table body
                    dynamicBody.append(revenueRow);
                    dynamicBody.append(cogsRow);
                    dynamicBody.append(gmRow);
                    dynamicBody.append(gmpRow);
                    dynamicBody.append(oetRow);
                    dynamicBody.append(oepRow);

                    __currency_convert_recursively($('#margin_report'));

                    // Initialize DataTable with buttons
                    $('#margin_report').DataTable({
                        dom: 'Bfrtip',
                        buttons: [
                            {
                                extend: 'csvHtml5',
                                text: 'Export CSV',
                                title: $('select#year_filter').val()+'-'+LANG.margin_report,
                                exportOptions: {
                                    format: {
                                        body: function (data, row, column, node) {
                                            // Use jQuery to find and remove the content you want to hide
                                            //var $node = $(node);
                                            var $clonedNode = $(node).clone();
                                            $clonedNode.find('span.no-print').remove();
                                            
                                            // Return the modified data
                                            return $clonedNode.text();
                                        }
                                    }
                                }
                            },
                            {
                                extend: 'excel',
                                text: 'Export Excel',
                                title: $('select#year_filter').val()+'-'+LANG.margin_report,
                                exportOptions: {
                                    format: {
                                        body: function (data, row, column, node) {
                                            // Use jQuery to find and remove the content you want to hide
                                            //var $node = $(node);
                                            var $clonedNode = $(node).clone();
                                            $clonedNode.find('span.no-print').remove();
                                            
                                            // Return the modified data
                                            return $clonedNode.text();
                                        }
                                    }
                                }
                            },
                            {
                                extend: 'print',
                                text: 'Print',
                                title: $('select#year_filter').val()+'-'+LANG.margin_report,
                                exportOptions: {
                                    format: {
                                        body: function (data, row, column, node) {
                                            // Use jQuery to find and remove the content you want to hide
                                            //var $node = $(node);
                                            var $clonedNode = $(node).clone();
                                            $clonedNode.find('span.no-print').remove();
                                            
                                            // Return the modified data
                                            return $clonedNode.text();
                                        }
                                    }
                                }
                            }
                        ]
                    });
                } else {
                    console.error("Expected 'columns' and 'data' to be arrays.");
                }
            } catch (e) {
                console.error("Error parsing JSON response:", e);
            }
        },
        error: function(xhr, status, error) {
            console.error("An error occurred: " + error);
        }
    });
}
</script>
@endsection