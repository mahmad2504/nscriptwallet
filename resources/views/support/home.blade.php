<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>EPT Support Dashboard</title>
		<link rel="stylesheet" href="{{ asset('libs/tabulator/css/tabulator.min.css') }}" />
		<link rel="stylesheet" href="{{ asset('libs/attention/attention.css') }}" />
    <style>
		.tabulator [tabulator-field="summary"]{
				max-width:200px;
		}

		.flex-container {
			height: 100%;
			padding: 0;
			margin: 0;
			display: -webkit-box;
			display: -moz-box;
			display: -ms-flexbox;
			display: -webkit-flex;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.row {
			width: auto;
			
		}
		.flex-item {
			text-align: center;
		}

    </style>
    </head>
    <body>
	<div class="flex-container">

		<div class="row"> 
		    <div style="font-weight:bold;font-size:20px;line-height: 50px;height:50px;background-color:#4682B4;color:white;" class="flex-item"> 
			 <img style="float:left;" height="50px" src="{{ asset('apps/support/images/mentor.png') }}"></img>
			 <div style="margin-right:150px;"> EPS Support Dashboard </div>
			</div>
			<div class="flex-item"> 
				<div style="float:right"><a id="download" href="#">Download</a> </div>
				<a href="{{route('support.active')}}">Active </a>&nbsp&nbsp
				<a href="{{route('support.closed')}}">Closed </a>&nbsp&nbsp
				<a href="{{route('support.updated')}}">Recent updated</a>
			</div>
			<div class="flex-item"> 
				<br>
			</div>
			<div class="" > 
				<hr>
				<label style="margin-left:0px;" for="start">Start:</label>	
				<input type="date" id="filter_start" name="filter_start">
				<label style="margin-left:10px;" for="start">End:</label>
				<input type="date" id="filter_end" name="filter_end">
				<button id="filter" name="search">Filter</button>
				<hr>
			</div>
			<div class="flex-item">
				<div style="box-shadow: 3px 3px #888888;" id="table"></div>
			</div>
			
			<div class="flex-item">
				<small> Last Updated {{ $last_updated}} CST <a id="update" href="#">Click to update</a> </small>
			</div>
		</div>
	</div>
    </body>
	<script src="{{ asset('libs//jquery/jquery.min.js')}}" ></script>
	<script src="{{ asset('libs/sheetjs/xlsx.full.min.js')}}" ></script>
	<script src="{{ asset('libs/tabulator/js/tabulator.min.js') }}" ></script>
	<script src="{{ asset('libs/attention/attention.js') }}" ></script>
	<script>
	//define data
	var tabledata = @json($tickets);
	var jira_url = "{{$jira_url}}";
	var thistoday="{{date("Y-m-d H:i:s")}}";
	var filter_start="2020-01-01";
	var filter_end="{{date("Y-m-d")}}";
	var page = "{{$page}}";
	var dummy = [
		{id:1, name:"Oli Bob", location:"United Kingdom", gender:"male", rating:1, col:"red", dob:"14/04/1984"},
		{id:2, name:"Mary May", location:"Germany", gender:"female", rating:2, col:"blue", dob:"14/05/1982"},
		{id:3, name:"Christine Lobowski", location:"France", gender:"female", rating:0, col:"green", dob:"22/05/1982"},
		{id:4, name:"Brendon Philips", location:"USA", gender:"male", rating:1, col:"orange", dob:"01/08/1980"},
		{id:5, name:"Margret Marmajuke", location:"Canada", gender:"female", rating:5, col:"yellow", dob:"31/01/1999"},
		{id:6, name:"Frank Harbours", location:"Russia", gender:"male", rating:4, col:"red", dob:"12/05/1966"},
		{id:7, name:"Jamie Newhart", location:"India", gender:"male", rating:3, col:"green", dob:"14/05/1985"},
		{id:8, name:"Gemma Jane", location:"China", gender:"female", rating:0, col:"red", dob:"22/05/1982"},
		{id:9, name:"Emily Sykes", location:"South Korea", gender:"female", rating:1, col:"maroon", dob:"11/11/1970"},
		{id:10, name:"James Newman", location:"Japan", gender:"male", rating:5, col:"red", dob:"22/03/1998"},
	];
	
	var columns=[
        {title:"Key", field:"key", sorter:"string",align:"left",
			sorter:function(a, b, aRow, bRow, column, dir, sorterParams){
				va = a.split("-")[1];
				vb = b.split("-")[1]
				return va - vb; //you must return the difference between the two values
			},
			formatter:function(cell, formatterParams, onRendered)
			{
				url = days = cell.getRow().getData().url;
				return '<a href="'+jira_url+cell.getValue()+'">'+cell.getValue()+'</a>';
			}
		},
        {title:"Summary", field:"summary", sorter:"string", align:"left"},
		{title:"Account", field:"account", sorter:"string", align:"left"},
		{title:"E", field:"premium_support", sorter:"number", align:"center"},
		{title:"SLA", field:"sla", sorter:"number", align:"center",
			formatter:function(cell, formatterParams, onRendered)
			{
				return cell.getValue()+' Days';
			}
		},
		{title:"First Contact date", field:"first_contact_date", sorter:"string", align:"center",visible:false},
		{title:"First Contact", field:"net_time_to_firstcontact", sorter:"string", align:"center",
			formatter:function(cell, formatterParams, onRendered)
			{	
				violation_firstcontact = cell.getRow().getData().violation_firstcontact;
				//if(cell.getRow().getData().created < cell.getValue())
				if((violation_firstcontact==1)&&(cell.getValue() == ''))
					$(cell.getElement()).css({"color":"red"});
				//if(cell.getValue() == '')
					return cell.getRow().getData().net_time_to_firstcontact;
				//else
				//	return cell.getValue();
			}
		},
		{title:"Net minutes first_contact", field:"net_minutes_to_firstcontact", sorter:"number", align:"center",visible:false},
		
		{title:"Created", field:"created", sorter:"string", align:"center",visible:false,
			formatter:function(cell, formatterParams, onRendered)
			{
				return cell.getValue().substring(0,10);
			}
		},
		{title:"Priority", field:"priority.name", sorter:"string", align:"center"},
		{title:"Net Time spent", field:"net_time_to_resolution", sorter:"string", align:"center",
			formatter:function(cell, formatterParams, onRendered)
			{
				value = cell.getValue();
				values = value.split(',');
				if(values.length ==1)
					return;
				if(values.length != 3)
					return 'Invalid';
				days = values[0].split(' ');
				hours = values[1].split(' ');
				min = values[2].split(' ');
				if((days[0] == 0)&&(hours[0]==0))
					return '';
				if((days[0] == 0)&&(hours[0]!=0))
					return hours[0]+' Hours';
				else
					return days[0]+'.'+hours[0]+' Days';
			},
			sorter:function(a, b, aRow, bRow, column, dir, sorterParams){
				va = aRow.getData().net_minutes_to_resolution;
				vb = bRow.getData().net_minutes_to_resolution;
				return va - vb; //you must return the difference between the two values
			}
		},
		{title:"Net minutes consumed", field:"net_minutes_to_resolution", sorter:"number", align:"center",visible:false},
		{title:"Gross Time spent", field:"gross_time_to_resolution", sorter:"string", align:"center",
			formatter:function(cell, formatterParams, onRendered)
			{
				value = cell.getValue();
				
				values = value.split(',');
				if(values.length ==1)
					return;
				if(values.length != 3)
					return 'Invalid';
				days = values[0].split(' ');
				hours = values[1].split(' ');
				if((days[0] == 0)&&(hours[0]==0))
					return '';
				if((days[0] == 0)&&(hours[0]!=0))
					return hours[0]+' Hours';
				else
					return days[0]+'.'+hours[0]+' Days';
			},
			sorter:function(a, b, aRow, bRow, column, dir, sorterParams){
				va = aRow.getData().gross_minutes_to_resolution;
				vb = bRow.getData().gross_minutes_to_resolution;
				return va - vb; //you must return the difference between the two values
			}
		},
		{title:"Gross minutes consumed", field:"gross_minutes_to_resolution", sorter:"number", align:"center",visible:false},
		//{title:"minutes first contact", field:"net_minutes_to_firstcontacte", sorter:"number", align:"center",visible:true},
		
		
		{title:"Quota(min)", field:"minutes_quota", sorter:"number", align:"center",visible:false},
		
		{title:"Resolved On", field:"resolutiondate", sorter:"string", align:"center",visible:false,
			formatter:function(cell, formatterParams, onRendered)
			{
				return cell.getValue().substring(0,10);
			}
		},
		{title:"Solution On", field:"solution_provided_date", sorter:"string", align:"center",visible:false,
			formatter:function(cell, formatterParams, onRendered)
			{
				return cell.getValue().substring(0,10);
			}
		},
		
		{title:"Time Consumed", field:"percent_time_consumed", sorter:"number", align:"left",visible:true,
			formatter:function(cell, formatterParams, onRendered)
			{
				statuscategory = cell.getRow().getData().statuscategory;
				time_consumed = cell.getValue();
				if(statuscategory == 'resolved')
				{
					return  '<span style="text-align: center;display: inline-block;width:'+'100'+'%;color:white;background-color:grey;"><small>'+time_consumed+'%</small></span>';
				}
				if(time_consumed <50)
				{
					bcolor='ForestGreen';
					fcolor='white';
				}
				else if(time_consumed <75)
				{
					bcolor='Gold';
					fcolor='black';
				}
				else if(time_consumed <100)
				{
					bcolor='orange';
					fcolor='black';
				}
				else
				{
					bcolor='red';
					fcolor='white';
				}
				
				return  '<span style="text-align: center;display: inline-block;width:'+time_consumed+'%;color:'+fcolor+';background-color:'+bcolor+';"><small>'+time_consumed+'%</small></span>';
			}
		},
		{title:"Status", field:"status", sorter:"string", align:"center",visible:true,
			formatter:function(cell, formatterParams, onRendered)
			{
				first_contact_date = cell.getRow().getData().first_contact_date;
				statuscategory = cell.getRow().getData().statuscategory;
				$(cell.getElement()).css({"background":"white"});
				$(cell.getElement()).css({"color":"black"});
				$(cell.getElement()).css({"border":"1px solid white"});
				
				if(statuscategory == 'resolved')
				{
					$(cell.getElement()).css({"background":"grey"});
					$(cell.getElement()).css({"color":"white"});
					return  cell.getValue();
				}
					
				if(first_contact_date == '')
				{
					$(cell.getElement()).css({"background":"orange"});
					return 'Waiting First Contact';
				}
				
				if( (cell.getValue() == 'Waiting Customer Feedback')||(cell.getValue() == 'Queued'))
				{
					$(cell.getElement()).css({"background":"yellow"});
					return  cell.getValue();
				}
				if(cell.getValue() == 'Pending Enhancements')
				{
					$(cell.getElement()).css({"background":"MediumTurquoise"});
					return  cell.getValue();
				}
				if(cell.getValue() == 'Pending Defect')
				{
					$(cell.getElement()).css({"background":"DarkKhaki"});
					return  cell.getValue();
				}
	
				$(cell.getElement()).css({"color":"white"});
				$(cell.getElement()).css({"background":"green"});
				return  cell.getValue();
			}
		},
		{title:"State", field:"statuscategory", sorter:"string", align:"center",visible:false,
			formatter:function(cell, formatterParams, onRendered)
			{
				percent_time_consumed = cell.getRow().getData().percent_time_consumed;
				$(cell.getElement()).css({"background":"green"});
				$(cell.getElement()).css({"color":"white"});
				$(cell.getElement()).css({"border":"1px solid white"});
				
				if(percent_time_consumed > 50)
				{
					$(cell.getElement()).css({"background":"yellow"});
					$(cell.getElement()).css({"color":"black"});
				}
				if(percent_time_consumed > 75)
					$(cell.getElement()).css({"background":"orange"});
				if(percent_time_consumed == 100)
				{
					$(cell.getElement()).css({"background":"red"});
					$(cell.getElement()).css({"color":"white"});
				}
				if(cell.getValue() == 'RESOLVED')
				{
					$(cell.getElement()).css({"background":"grey"});
					$(cell.getElement()).css({"color":"white"});
				}
				return cell.getValue();
			}
		},
		{title:"Updated", field:"updated", sorter:"string", align:"center",visible:false,
			formatter:function(cell, formatterParams, onRendered)
			{
				return  cell.getValue();
				ms = Math.floor(( Date.parse(thistoday) - Date.parse(cell.getValue()) ));
				t = millisToDaysHoursMinutes(ms);
				if(t.d > 0)
					return t.d+" days";
				else if(t.h > 0)
				{
					return t.h+" hours";
				}
				else if(t.m > 0)
				{
					return t.m+" min";
				}
				else if(t.s > 0)
				{
					return t.s+" sec";
				}
				else
					return "5 sec";
				
				return cell.getValue().substring(0,10);
			}
		},
		{title:"Product", field:"product_name", sorter:"string", align:"center",visible:false}, 
		{title:"Component", field:"component", sorter:"string", align:"center",visible:false},
		{title:"Type", field:"issuetype", sorter:"string", align:"center",visible:false},
		{title:"Resolution", field:"resolution", sorter:"string", align:"center",visible:false},
        /*{title:"Gender", field:"gender", sorter:"string", cellClick:function(e, cell){console.log("cell click")},},
        {title:"Height", field:"height", formatter:"star", align:"center", width:100},
        {title:"Favourite Color", field:"col", sorter:"string"},
        {title:"Date Of Birth", field:"dob", sorter:"date", align:"center"},
        {title:"Cheese Preference", field:"cheese", sorter:"boolean", align:"center", formatter:"tickCross"},*/
    ];
	function millisToDaysHoursMinutes(miliseconds) 
	{
	  var days, hours, minutes, seconds, total_hours, total_minutes, total_seconds;
	  total_seconds = parseInt(Math.floor(miliseconds / 1000));
	  total_minutes = parseInt(Math.floor(total_seconds / 60));
	  total_hours = parseInt(Math.floor(total_minutes / 60));
	  days = parseInt(Math.floor(total_hours / 24));
	  seconds = parseInt(total_seconds % 60);
	  minutes = parseInt(total_minutes % 60);
	  hours = parseInt(total_hours % 24);
	  return { d: days, h: hours, m: minutes, s: seconds };
	};
	$(document).ready(function()
	{
		//define table
		$('#download').on('click',function()
		{
			table.showColumn("gross_minutes_to_resolution");
			table.showColumn("net_minutes_to_resolution");
			table.showColumn("net_minutes_to_firstcontact");
			table.showColumn("resolution");
			table.showColumn("issuetype");
			table.showColumn("component");
			table.showColumn("product_name");
			table.showColumn("resolutiondate");
			table.showColumn("solution_provided_date");
			table.showColumn("first_contact_date");
			table.showColumn("created");
			table.showColumn("updated");
			table.download("xlsx", "support.xlsx", {sheetName:"tickets"});
			table.hideColumn("gross_minutes_to_resolution");
			table.hideColumn("net_minutes_to_resolution");
			table.hideColumn("net_minutes_to_firstcontact");
			table.hideColumn("resolution");
			table.hideColumn("issuetype");
			table.hideColumn("component");
			table.hideColumn("product_name");
			table.hideColumn("solution_provided_date");
			table.hideColumn("first_contact_date");
			if(page == 'active')
			{
				table.showColumn("created");
				table.hideColumn("resolutiondate");
				table.hideColumn("updated");
			}
			else if(page == 'closed')
			{
				table.hideColumn("created");
				table.showColumn("resolutiondate");
				table.hideColumn("updated");
			}
			else if(page == 'updated')
			{
				table.hideColumn("created");
				table.hideColumn("resolutiondate");
				table.showColumn("updated");
			}
		});
		$('#update').on('click',function()
		{
			new Attention.Alert({
					title: 'Alert',
					content: 'Update Initiated',
					afterClose: () => {
						
					}
					});
					
			$.ajax({
				type:"GET",
				url:'{{route("support.sync")}}',
				cache: false,
				data:null,
				success: function(response){
					
					new Attention.Alert({
					title: 'Alert',
					content: 'Updated. Please refresh the page',
					afterClose: () => {
						
					}
					});
				
				},
				error: function(response){
					new Attention.Alert({
					title: 'Alert',
					content: 'Failed',
					afterClose: () => {
						
					}
					});
				}
			});	
		});
		$('#filter').on('click',function()
		{
			if(page == 'active')
			{
				filter_start = $('#filter_start').val();
				filter_end = $('#filter_end').val();
				if((filter_start == '')&&(filter_end != ''))
					table.setFilter([{field:"created", type:"<=", value:filter_end}]);
				else if((filter_start != '')&&(filter_end == ''))
					table.setFilter([{field:"created", type:">=", value:filter_start}]);
				else 
					table.setFilter(
					[
						{field:"created", type:">=", value:filter_start},
						[
							{field:"created", type:"<=", value:filter_end}
						]
					]
					);
			}
			else if(page == 'closed')
			{
				filter_start = $('#filter_start').val();
				filter_end = $('#filter_end').val();
				if((filter_start == '')&&(filter_end != ''))
					table.setFilter([{field:"resolutiondate", type:"<=", value:filter_end}]);
				else if((filter_start != '')&&(filter_end == ''))
					table.setFilter([{field:"resolutiondate", type:">=", value:filter_start}]);
				else 
					table.setFilter(
					[
						{field:"resolutiondate", type:">=", value:filter_start},
						[
							{field:"resolutiondate", type:"<=", value:filter_end}
						]
					]
					);
			}
			else if(page == 'updated')
			{
				filter_start = $('#filter_start').val();
				filter_end = $('#filter_end').val();
				if((filter_start == '')&&(filter_end != ''))
					table.setFilter([{field:"updated", type:"<=", value:filter_end}]);
				else if((filter_start != '')&&(filter_end == ''))
					table.setFilter([{field:"updated", type:">=", value:filter_start}]);
				else 
					table.setFilter(
					[
						{field:"updated", type:">=", value:filter_start},
						[
							{field:"updated", type:"<=", value:filter_end}
						]
					]
					);
			}
		});
		
		var table = new Tabulator("#table", {
			data:tabledata,
			columns:columns,
			tooltips:true,
			//autoColumns:true,
		});
		if(page == 'active')
		{
			table.showColumn("created");
			$('#filter').text('Filter on creation date');
		}
		else if(page == 'closed')
		{
			table.showColumn("resolutiondate");
			$('#filter').text('Filter on resolution date');
		}
		else if(page == 'updated')
		{
			table.showColumn("updated");
			$('#filter').text('Filter on last update date');
		}
	});
	
	</script>
</html>
