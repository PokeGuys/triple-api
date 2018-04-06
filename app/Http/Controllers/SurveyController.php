<?php

namespace App\Http\Controllers;

use Auth;
use Log;
use Validator;
use Excel;
use App\Models\Survey;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Dingo\Api\Routing\Helpers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\RedirectResponse;
use Maatwebsite\Excel\ExcelServiceProvider;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Dingo\Api\Exception\StoreResourceFailedException;

class SurveyController extends Controller
{
    use Helpers;

    //Collect survey result
    public function input(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gender'        => 'required',
            'age_group'     => 'required',
            'income_group'  => 'required',
            'nation'        => 'required',
            'education'     => 'required|integer|min:1|max:9',
            'destination'   => 'required|integer|min:1|max:20',
            'family_holiday_maker'   => 'required|integer|min:0|max:1',
            'foodie'              => 'required|integer|min:0|max:1',
            'backpacker'          => 'required|integer|min:0|max:1',
            'history_buff'        => 'required|integer|min:0|max:1',
            'nightlife_seeker'   => 'required|integer|min:0|max:1',
            'eco_tourist'         => 'required|integer|min:0|max:1',
            'trendsetter'         => 'required|integer|min:0|max:1',
            'nature_lover'        => 'required|integer|min:0|max:1',
            'urban_explorer'      => 'required|integer|min:0|max:1',
            'thrill_seeker'       => 'required|integer|min:0|max:1',
            'beach_goer'          => 'required|integer|min:0|max:1',
            'sixtyPlus_traveller'   => 'required|integer|min:0|max:1',
            'like_a_local'        => 'required|integer|min:0|max:1',
            'luxury_traveller'   => 'required|integer|min:0|max:1',
            'vegetarian'          => 'required|integer|min:0|max:1',
            'shopping_fanatic'   => 'required|integer|min:0|max:1',
            'thrifty_traveller'   => 'required|integer|min:0|max:1',
            'art_and_architecture_lover'   => 'required|integer|min:0|max:1',
            'peace_and_quiet_seeker'   => 'required|integer|min:0|max:1'
        ]);
        if ($validator->fails())
        {
            throw new StoreResourceFailedException($validator->errors()->first());
        }
        try {
            DB::beginTransaction();

            DB::table('surveys')->insert(
                array(
                  'gender'        => $request->gender,
                  'age_group'     => $request->age_group,
                  'income_group'  => $request->income_group,
                  'nation'        => $request->nation,
                  'education'  => $request->education,
                  'destination'  => $request->destination,
                  'family_holiday_maker'  => $request->family_holiday_maker,
                  'foodie'  => $request->foodie,
                  'backpacker'  => $request->backpacker,
                  'history_buff'  => $request->history_buff,
                  'nightlife_seeker'  => $request->nightlife_seeker,
                  'eco_tourist'  => $request->eco_tourist,
                  'trendsetter'  => $request->trendsetter,
                  'nature_lover'  => $request->nature_lover,
                  'urban_explorer'  => $request->urban_explorer,
                  'thrill_seeker'  => $request->thrill_seeker,
                  'beach_goer'  => $request->beach_goer,
                  'sixtyPlus_traveller'  => $request->sixtyPlus_traveller,
                  'like_a_local'  => $request->like_a_local,
                  'luxury_traveller'  => $request->luxury_traveller,
                  'vegetarian'  => $request->vegetarian,
                  'shopping_fanatic'  => $request->shopping_fanatic,
                  'thrifty_traveller'  => $request->thrifty_traveller,
                  'art_and_architecture_lover'  => $request->art_and_architecture_lover,
                  'peace_and_quiet_seeker'  => $request->peace_and_quiet_seeker
                )
            );

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans($e));
        }
        return new RedirectResponse('https://lokhay00.github.io/thankyou');
    }

    /**
     * Export survety result as .csv
     *
     * @Get("/")
     */
    public function export()
    {
        try{
            $items = Survey::all();
            Excel::create('survey_result', function($excel) use($items) {
                $excel->sheet('ExportFile', function($sheet) use($items) {
                    $sheet->fromArray($items);
                });
            })->download('csv');
        } catch(Exception $e){
            Log::error($e);
            throw new ServiceUnavailableHttpException('', trans('custom.unavailable'));
        }
    }
}
