<?php

namespace App\Http\Controllers\Cveportal;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Apps\Cveportal\Product;
use Redirect,Response, Artisan;
use Carbon\Carbon;
use App\Apps\Cveportal\Cve;
use App\Libs\Ldap\Ldap;
use App\Apps\Cveportal\Cvestatus;
use App\Apps\Cveportal\Staticpages;
use App\Apps\Cveportal\Cache;
use App\Apps\Cveportal\Jiraa;

class CveportalController extends Controller
{
	public function Index(Request $request)
	{
		$p = new Product();
		$group_names = $p->GetGroupNames();
		$product_names = [];
		$version_names = [];
		foreach($group_names as $group_name)
		{
			$productnames = $p->GetProductNames($group_name);
			foreach($productnames as $productname)
			{
				$version_names[] = $p->GetVersionNames($group_name,$productname);
			}
			$product_names[] = $productnames;
		}
		$jira_url = $p->jira_url;
		$refresh = 0;
		return view('cveportal.index',compact('group_names','product_names','version_names','jira_url'));
	}
	public function sync_staticpages(Request $request)
	{
		$sp = new Staticpages();
		$sp->Script();
	}
	
	public function TriageCve(Request $request,$cve)
	{
		$data = $request->session()->get('data');
		if($data == null)
			return 'You are not logged';
		if(!isset($data->user_name))
			return 'You are not logged';
		$c =  new CVE();
		$cvedata = $c->Get(null,$cve);
		$cvedata = $cvedata[0];
		$product = new Product();
		$products = $product->GetProducts($data->user_name);
		$myproducts = [];
		foreach($products as $product)
		{
			$myproducts[$product->id] = $product;
		}
		//$cvestatus = new CVEStatus();
		//$cvedata->products = $cvestatus->GetAllStatus($cve);
		foreach($cvedata->product as $product)
		{
			if(!isset($myproducts[$product->status->productid]))
				$product->readonly=1;
			else
				$product->readonly=0;
		}
		return [$cvedata];
	}
	public function Triage(Request $request)
	{
		$data = $request->session()->get('data');
		if($data == null)
			return view('cveportal.login');
		if(!isset($data->user_name))
			return view('cveportal.login');
		$p = new Product();
		$group_names = $p->GetGroupNames($data->user_name);
		$product_names = [];
		$version_names = [];
		foreach($group_names as $group_name)
		{
			$productnames = $p->GetProductNames($group_name);
			foreach($productnames as $productname)
			{
				$version_names[] = $p->GetVersionNames($group_name,$productname);
			}
			$product_names[] = $productnames;
		}
		$displayname=$data->user_displayname;
		$admin = $data->user_name;
		$jira_url = $p->jira_url;
		return view('cveportal.triage',compact('displayname','group_names','product_names','version_names','admin','jira_url'));
	}
	public function Login(Request $request)
	{
		return view('cveportal.login');
	}
	public function Logout(Request $request)
	{
		$request->session()->forget('data');
		echo "Your are logged out of system";
	}
	public function Authenticate(Request $request)
	{
		//dump($request->data);
		if(!isset($request->data['USER'])||!isset($request->data['PASSWORD']))
			return Response::json(['error' => 'Invalid Credentials'], 404); 

		$ldap =  new Ldap();
		$data = $ldap->Login($request->data['USER'],$request->data['PASSWORD']);
		if($data== null)
		{
			$request->session()->forget('data');
			return Response::json(['error' => 'Invalid Credentials'], 404); 
		}
		else
			$request->session()->put('data', $data);
		//dump("Success");
		return [];
		//return $data->user_displayname;
	}
	/*public function GetCves(Request $request,$group='all',$product='all',$version='all',$admin='all')
	{
		$output= $this->GetCves_($request,$group,$product,$version,$admin);
		dump($output['total']);
		dump($output['last_index']);
		dd(count($output['data']));
	}*/
	public function GetCves(Request $request,$group='all',$product='all',$version='all',$admin='all')
	{
		$limit = 0;
		$skip = 0;
		$cve=null;
		$severity=null;
		if($request->page!=null)
		{
			$skip = $request->size*($request->page-1);
			$limit= $request->size*1;
			
			//dump($skip);
			//dd($limit);
		}
		if($request->cve!=null)
			$cve = $request->cve;
		
		if($request->severity!=null)
			$severity = $request->severity;
		
		//$cache = new Cache();
		//$static_file_name = $group."_".$product."_".$version;
		//ob_start('ob_gzhandler');
		$p = new Product();
		$group = $group=='all'?null:$group;
		$product = $product=='all'?null:$product;
		$version = $version=='all'?null:$version;
		$admin = $admin=='all'?null:$admin;
		$ids_ = [];
		$pu = $p->GetProductByUser($admin);
		foreach($pu as $_p)
		{
			$ids_[$_p->id] = $_p->id;
		}
		
		$ids = $p->GetIds($group,$product,$version,$admin);
		
			
		//sort($ids);
		
		//$key = md5(implode(",",$ids));
		//$data =  null;
		//if($admin ==  null)
		//	$data = $cache->Get($key);
		//$data=null;
		//if($data==null)
		
		{
			$c =  new CVE();
			$data = $c->GetPublished($ids,$cve,$severity,$limit,$skip);
			$total = $data['total'];
			unset($data['total']);
			//$cache->Put($key,json_encode($data));
		}
		foreach($data as $d)
		{
			foreach($d->product as $p)
			{ 
				if(isset($ids_[$p->status->productid]))
				{
					if($admin == null)
						$p->status->readonly=1;
					else
						$p->status->readonly=0;
				}
				else
				{
					$p->status->readonly=1;
				}
			}
		}
		$output= [];
		$output['total']=$total;
		$output['page_size']=$request->size*1;	
		$output['last_index']=$skip+$output['page_size'];
		$output['last_page'] = -1;
		if($request->size > 0)
			$output['last_page']= ceil($total/($request->size*1));
		$output['data']= array_values($data);
		
		return $output;
	}
	public function RssFeed(Request $request,$productid)
	{
		$sp = new Staticpages();
		return $sp->GetRssfeed(null,null,null,$productid);
	}
	public function StatusUpdate(Request $request)
	{
		$data = $request->session()->get('data');
		if($data == null)
			return Response::json(['error' => 'Un Authorized access'], 404); 
		if(!isset($data->user_name))
			return Response::json(['error' => 'Un Authorized access'], 404); 
		
		$p = new Product();
		//$group = $request->group=='all'?null:$request->group;
		//$product = $request->product=='all'?null:$request->product;
		//$version = $request->version=='all'?null:$request->version;
		//$ids = $p->GetIds($group,$product,$version);
		//sort($ids);
		//$key = md5(implode(",",$ids));
		$cvestatus = new CVEStatus();
		$cvestatus->UpdateStatus($request->status);
		$status = $cvestatus->GetStatus($request->status['cve'],$request->status['productid']);
		//dump($status);
		$cache = new Cache();
		$cache->Clean();
		return ["status"=>"success"];
	}
	public function JiraSyncRequest(Request $request)
	{
		$jira=new Jiraa();
		$jira->RequestSync();
		return "Requested. Wait for a minute";
	}
	public function GetProductData(Request $request,$id)
	{
		ob_start('ob_gzhandler');
		$p = new Product();
		$product = $p->GetProduct($id,"1");
		if($product == null)
		{
			$a['code'] = 404;
			$a['desc'] = 'object not found';
			return $a ;
		}
		return json_encode($product);
	}
	public function GetCveData(Request $request,$productid)
	{
		$c =  new CVE();
		$ids[] = $productid;
		$data = $c->GetPublished($ids);
		return $data;
	}
}