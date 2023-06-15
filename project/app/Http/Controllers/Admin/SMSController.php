<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\Generalsetting;
use Datatables;
use Illuminate\Http\Request;

class SMSController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
    }

    //*** JSON Request
    public function datatables()
    {
         $datas = EmailTemplate::orderBy('id','desc');
         //--- Integrating This Collection Into Datatables
         return DataTables::of($datas)
                            ->addColumn('action', function(EmailTemplate $data) {
                                return '<div class="action-list"><a class="btn btn-primary btn-sm btn-rounded" href="' . route('admin.sms.edit',$data->id) . '"> <i class="fas fa-edit"></i>Edit</a></div>';
                            })
                            ->toJson();//--- Returning Json Data To Client Side
    }

    public function index()
    {
        return view('admin.sms.index');
    }

    public function edit($id)
    {
        $data = EmailTemplate::findOrFail($id);
        return view('admin.sms.edit',compact('data'));
    }

    public function update(Request $request, $id)
    {
        $data = EmailTemplate::findOrFail($id);
        $input = $request->all();
        $data->update($input);
        //--- Redirect Section
        $msg = 'Data Updated Successfully.'.'<a href="'.route("admin.sms.index").'">View Template Lists</a>';
        return response()->json($msg);
        //--- Redirect Section Ends
    }
}
