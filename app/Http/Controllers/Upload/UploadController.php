<?php

namespace App\Http\Controllers\Upload;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Request;
use DB;
use Illuminate\Support\Facades\Request as IlluminateRequest;

class UploadController extends Controller
{

    function loadPage($status)
    {
        // all fields in the Actor table are relevant
        $actors = DB::table('Actor')
            ->orderBy('First_Name', 'asc')
            ->get();

        // get only relevant information about videos where IsMovie is 0 (i.e. a show)
        $shows = DB::table('Video')
            ->select('Video_ID', 'Title')
            ->where('IsMovie', 0)
            ->orderBy('Title', 'asc')
            ->get();

        $genres = DB::table('Genre')
            ->select('Genre_ID', 'Name')
            ->orderBy('Name', 'asc')
            ->get();

        $directors = DB::table('Director')
            ->get()
            ->unique('Director_ID');

        return view(
            'upload',
            [
                'status' => $status, 'actors' => $actors, 'shows' => $shows,
                'genres' => $genres, 'directors' => $directors
            ]
        );
    }

    function index()
    {
        return self::loadPage(0);
    }

    function submit()
    {
        // Display File Size
        //        echo 'File Size: ' . $file->getSize();
        //        echo '<br>';

        // get video metadata
        $title = Request::get('title');
        $year = Request::get('year');
        $summary = Request::get('summary');
        $sub = Request::get('subscription');
        $mediatype = Request::get('mediatype');
        $runtime = Request::get('duration');
        $seasonNumber = Request::get('seasonNumber');
        $episodeNumber = Request::get('episodeNumber');
        $addNewShow = (Request::get('addNewShow') == "on");
        $newShowName = Request::get('showInput');
        $newShowSummary = Request::get('showSummary');
        $showId = Request::get('showSelect');
        $videoId = null;
        $episodeId = null;

        // upload video
        $videoFile = Request::file('video');
        $filename = $videoFile->getClientOriginalName();
        // get the lowercase name of the show
        if ($mediatype == "show") {
            if ($addNewShow) {
                $showDir = strtolower($newShowName);
            } else {
                $existingShowName = DB::table('Video')->select('Title')->where('Video_ID', $showId)->first()->Title;
                $showDir = strtolower($existingShowName);
            }
        }
        $videoDir = ($mediatype == "movie") ? "movies" : "tv-shows/" . $showDir . "/Season" . $seasonNumber;
        // use laravel Storage facade method to store file
        Storage::disk('videos')->putFileAs($videoDir, $videoFile, $filename);
        $path = base_path('assets/videos/' . $videoDir . '/' . $filename);

        // enter video info in DB
        if ($mediatype == "movie" || ($mediatype == "show" && $addNewShow)) {
            $videoId = DB::table('Video')->insertGetId(
                ['Title' => $title, 'Year' => $year, 'Summary' => ($mediatype == "movie") ? $summary : $newShowSummary, 'Subscription' => $sub, 'IsMovie' => ($mediatype == "movie") ? 1 : 0]
            );
            $showId = $videoId;
            if ($mediatype == "show") {
                //echo DB::table('video')->select('Video_ID')->where('Title', $title)->get();
                //$newShowId = $videoId; // DB::table('video')->select('Video_ID')->where('Title', $title)->get()->toArray()->first();
            }
        }

        // enter movie info in DB
        if ($mediatype == "movie") {
            DB::table('Movie')->insert(
                ['Movie_ID' => $videoId, 'File_Path' => $path, 'Length' => $runtime]
            );
        }

        // enter episode info in DB
        else if ($mediatype == "show") {
            $episodeId = DB::table('Episode')->insertGetId(
                [
                    'Show_ID' => $showId, 'Season_Number' => $seasonNumber, 'Episode_Number' => $episodeNumber,
                    'Episode_Title' => $title, 'File_Path' => $path, 'Length' => $runtime, 'Episode_Summary' => $summary
                ]
            );
        }

        // enter cast info in DB
        $count = 1;
        $actorId = null;
        while (null != ($actorId = Request::get('actorSelect' . $count)) || null != ($fn = Request::get('actorInputFirst' . $count))) {
            //$actor = Request::get('actorSelect' . $count);
            if (Request::get('addNew' . $count) == 'on') {
                $ln = Request::get('actorInputLast' . $count);
                $actorId = DB::table('Actor')->insertGetId(
                    ['First_Name' => $fn, 'Last_Name' => $ln]
                );
                //echo "First name: " . $fn;
                //echo "Last name: " . $ln;
            } else {
                // use the selected actor name
                //$actorId = Request::get('actorSelect' . $count)
            }

            //echo "Episode ID: " . $episodeId;
            //echo "Actor ID: " . $actorId;
            //echo "Video ID: " . $videoId;

            DB::table('Cast')->insert(
                [
                    'IsMovie' => ($mediatype == "movie"),
                    'Episode_ID' => $episodeId,
                    'Actor_ID' => $actorId,
                    'Movie_ID' => $videoId
                ]
            );
            $count++;
        }

        // enter director info in DB
        $count = 1;
        $directorId = null;
        while (($directorId = Request::get('directorSelect' . $count)) != null || ($fn = Request::get('directorInputFirst' . $count)) != null) {
            if (Request::get('addNewD' . $count) == 'on') {
                $ln = Request::get('directorInputLast' . $count);
                $directorId = DB::table('Director')->insertGetId(
                    ['First_Name' => $fn, 'Last_Name' => $ln]
                );
            }
            DB::table('Directors')->insert(
                [
                    'IsMovie' => ($mediatype == "movie"),
                    'Episode_ID' => $episodeId,
                    'Director_ID' => $directorId,
                    'Movie_ID' => $videoId
                ]
            );
            $count++;
        }

        // enter genre info in DB
        $count = 1;
        $genreId = null;
        while (($genreId = Request::get('genreSelect' . $count)) != null) {
            DB::table('Genres')->insert(
                [
                    'Genre_ID' => $genreId,
                    'Video_ID' => $videoId,
                ]
            );
            $count++;
        }

        // upload thumbnail
        if ($videoId != null) {
            $thumbnailFile = Request::file('thumbnail');
            Storage::disk('thumbnails')->putFileAs('', $thumbnailFile, $videoId . ".jpg");
        }

        return self::loadPage(1);
    }
}
