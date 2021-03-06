<?php

namespace App\Http\Controllers;

use DB;
use Auth;

class ListEpisodesController extends WatchVideo
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    function getView($video_id)
    {
        if (Auth::guest()) {
            return redirect()->route('login');
        }

        $userID = Auth::user()->id;
        $isSubbed = $this -> isSubbed($video_id, $userID);

        if (!$isSubbed)
        {
            return redirect("/video_details?video=".$video_id);
        }
        $series = $this->getSeries($video_id);
        $seriesTitle = DB::table('Video')
            ->where('Video.Video_ID', '=', "$video_id")
            ->select('Video.*')
            ->first()->Title;

        return view('all_episodes', [
            'series_title' => $seriesTitle,
            'series' => $series
        ]);
    }

    function getSeries($video_id)
    {
        return DB::table('Episode')
            ->where('Episode.Show_ID', '=', "$video_id")
            ->select('Episode.*')
            ->orderBy('Season_Number', 'asc')
            ->orderBy('Episode_Number', 'asc')
            ->get();
    }

}
