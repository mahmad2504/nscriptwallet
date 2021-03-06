<?php

namespace App\Http\Controllers\Ishipment;
use App\Apps\Ishipment\Ishipment;
use App\Http\Controllers\Controller;

use Auth;
use Illuminate\Http\Request;
use Response;

class IshipmentController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
	
    }
	public function Sync(Request $request)
	{
		$app = new Ishipment();
		$app->Save(['sync_requested'=>1]);
		return ['status'=>'Sync Requested'];
	}
	public function IsSynced(Request $request)
	{
		$app = new Ishipment();
		$val = $app->Read('sync_requested');
		$val  = $val == 1? 0:1;
		return ['status'=>$val];
	}
	public function Active(Request $request)
	{
		$app = new Ishipment();
		$tickets = $app->ReadActive();
		$filtered = [];
		for($i=0;$i<count($tickets);$i++)
		{
			$ticket = $tickets[$i];
			$obj =  new \StdClass();
			$ticket = $ticket->jsonSerialize();
			unset($ticket->_id);
			/// Hardware Details //////
			//$parts = explode("Qty:",$ticket->desc);
			//$parts = explode("\n",$parts[0]);	
			//$del = '';
			//$hardware = '';
			//for($j=1;$j<count($parts);$j++)
			//{
			//	if(strlen(trim($parts[$j]))>0)
			//	{
			//		$parts[$j] = str_replace("-",'',$parts[$j]);
			//		$hardware .= $del.trim($parts[$j]);
			//		$del=',';
			//	}
			//}
			//$obj->hardware = $hardware;
			//dump($obj->hardware);
			//dump($ticket->url);
			// Owener ////
			$parts = explode("-",$ticket->name);
			$obj->hardware = $parts[0];
			$obj->owner = $parts[2];
			$obj->source = $parts[1];
			$obj->team = '';
			$exportticket = 0;
			foreach($ticket->labels as $label)
			{
				if(strtolower($label->name)=='export')
				{
					$exportticket = 1;
					break;
				}
			}
			if($exportticket==1)
				continue;
			foreach($ticket->labels as $label)
			{
				$obj->team = trim($label->name);
				break;
			}
			if(isset($ticket->checkitems['Shipment Dispatched']->state))
			{
				if($ticket->checkitems['Shipment Dispatched']->state == 'complete')
				{
					$obj->shipment_date = $ticket->checkitems['Shipment Dispatched']->date;
				}
			}
			if(isset($ticket->checkitems['Delivered']->state))
			{
				//dump($ticket->checkitems['Delivered']->state);
				if($ticket->checkitems['Delivered']->state == 'complete')
				{
					$obj->received_date = $ticket->checkitems['Delivered']->date;
				}
			}

			$obj->trackingno = $ticket->trackingno;
			if(($ticket->idList == $app->lists['Upcoming'])||($ticket->idList == $app->lists['Shipment'])) 
			    $obj->status = 'Ready';
			if(isset($ticket->checkitems['Shipment Dispatched']->state))
			{
				if($ticket->checkitems['Shipment Dispatched']->state == 'complete')
					$obj->status = 'Dispatched';
			}
			if($ticket->idList == $app->lists['Custom'])
				$obj->status = 'Customs';
			if($ticket->idList == $app->lists['Expense'])
				$obj->status = 'Received';
			$obj->url = $ticket->url;
			$filtered[]=$obj;
		}
		$tickets = $filtered;
		$lastupdated = $app->ReadUpdateTime('lastupdate');
		return view('ishipment.index',compact('tickets','lastupdated'));
	}
}