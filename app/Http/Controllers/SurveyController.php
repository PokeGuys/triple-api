<?php

namespace App\Http\Controllers;

use Auth;
use Log;
use Validator;
use Carbon\Carbon;
use App\Http\Traits\TripHelper;
use Dingo\Api\Routing\Helpers;
use Illuminate\Http\Request;

class SurveyController extends Controller
{
    use Helpers, TripHelper;

    //Collect survey result
    public function input(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gender'        => 'required',
            'age_group'     => 'required',
            'income_group'  => 'required',
            'occupation'    => 'required|integer|min:1|max:3',
            'destination'   => 'required|integer|min:1|max:20',
            'must_see'      => 'required|integer|min:0|max:5',
            'cuisine'       => 'required|integer|min:0|max:5',
            'adventure'     => 'required|integer|min:0|max:5',
            'entertainment' => 'required|integer|min:0|max:5',
            'history'       => 'required|integer|min:0|max:5',
            'shopping'      => 'required|integer|min:0|max:5'
        ]);

        try {
            DB::beginTransaction();
            $survey = Survey::create([
                'gender'        => $request->gender,
                'age_group'     => $request->age_group,
                'income_group'  => $request->income_group,
                'occupation'    => $request->occupation,
                'destination'   => $request->destination,
                'must_see'      => $request->must_see,
                'cuisine'       => $request->cuisine,
                'adventure'     => $request->adventure,
                'entertainment' => $request->entertainment,
                'history'       => $request->history,
                'shopping'      => $request->shopping
            ]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
        return $this->response->created();
    }

    //Export csv
    public function export()
    {
    }
}
