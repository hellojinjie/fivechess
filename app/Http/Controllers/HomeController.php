<?php

namespace App\Http\Controllers;

use App\ChessTable;
use App\Game;
use App\Step;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return response()->json(['status'=> 'success', 'message' => "You are logined", 'user' => Auth::user()], 200);
    }

    public function chessTable()
    {
        $tables = ChessTable::all()->sortBy('id');
        foreach ($tables as $table)
        {
            if (Carbon::createFromTimeString($table->last_check)->diffInMinutes(now()) >= 5)
            {
                if ($table->game_id > 0)
                {
                    $game = Game::find($table->game_id);
                    $game->next = '';
                    $game->save();
                }
                $table->game_id = 0;
                $table->save();
            }
        }

        return $tables;
    }

    public function tableStatus($tableId)
    {
        $chessTable = ChessTable::with("blackUserInfo")->with('whiteUserInfo')->find($tableId);
        if (Carbon::createFromTimeString($chessTable->last_check)->diffInMinutes(now()) >= 5)
        {
            if ($chessTable->game_id > 0)
            {
                $game = Game::find($chessTable->game_id);
                $game->next = '';
                $game->save();
            }
            $chessTable->game_id = 0;
            $chessTable->save();
            return array("table" => $chessTable);
        }
        $game = null;
        $steps = null;
        if ($chessTable->game_id > 0)
        {
            $game = Game::find($chessTable->game_id);
            $steps = Step::where('game_id', $game->id)->orderBy("step_num", "asc")->get();
        }
        $chessTable->last_check = now();
        $chessTable->save();
        // 重新查一遍
        $chessTable = ChessTable::with("blackUserInfo")->with('whiteUserInfo')->find($tableId);
        return array('table' => $chessTable, 'game' => $game, 'steps' => $steps);
    }

    /**
     * 1. 先判断是不是已经在桌上
     * 2. 再判断要坐的位置是不是已经有人了
     * @param $tableId
     * @param $blackOrWhite
     * @return array
     */
    public function joinTable($tableId, $blackOrWhite)
    {
        $count = ChessTable::where("user_black", Auth::user()->id)->orWhere("user_white", Auth::user()->id)->count();
        if ($count > 0)
        {
            return array('status' => 'failed', 'message' => '你已经在桌上了');
        }

        $table = ChessTable::find($tableId);
        if ($blackOrWhite == 'black')
        {
            if ($table->user_black != 0)
            {
                return array('status' => 'failed', 'message' => '这个位置已经有人了');
            }
            else
            {
                $table->user_black = Auth::user()->id;
            }
        }
        else if ($blackOrWhite == 'white')
        {
            if ($table->user_white != 0)
            {
                return array('status' => 'failed', 'message' => '这个位置已经有人了');
            }
            else
            {
                $table->user_white = Auth::user()->id;
            }
        }
        $table->save();

        // 如果 table 的两边都有人了，那就开始一个 game
        if ($table->user_black != 0 && $table->user_white != 0)
        {
            $game = new Game();
            $game->current_num = 0;
            $game->next = 'black';
            $game->save();
            $table->game_id = $game->id;
            Log::info("id of new game " . $game->id);
            $table->last_check = now();
            $table->save();
        }
        return array('status' => 'success');
    }

    public function leaveTable($tableId)
    {
        $table = ChessTable::find($tableId);
        if ($table->user_black == Auth::user()->id)
        {
            $table->user_black = 0;
        }
        else if ($table->user_white == Auth::user()->id)
        {
            $table->user_white = 0;
        }

        if ($table->game_id > 0)
        {
            $game = Game::find($table->game_id);
            $game->next = '';
            $game->save();
            $table->game_id = 0;
        }
        $table->save();


        return array('status' => 'success');
    }

    public function walk($tableId, $x, $y)
    {
        $table = ChessTable::find($tableId);
        Log::info(Auth::user()->id);
        Log::info($table);
        if (!$table)
        {
            return array("status" => 'failed', 'message' => '游戏不存在');
        }
        if ($table->user_black != Auth::user()->id && $table->user_white != Auth::user()->id)
        {
            return array("status" => 'failed', 'message' => '你没有加入这个游戏');
        }
        if (!($table->game_id > 0))
        {
            return array("status" => 'failed', 'message' => '当前游戏还没有开始呢');
        }
        $game = Game::find($table->game_id);
        if ($game->next == 'black' && $table->user_black != Auth::user()->id || $game->next == 'white' && $table->user_white != Auth::user()->id )
        {
            return array("status" => 'failed', 'message' => '当前不是你走子');
        }
        $step = new Step();
        $step->x = $x;
        $step->y = $y;
        $step->game_id = $game->id;
        $step->step_num = $game->current_num + 1;
        $step->save();

        // 判断下输赢
        $winResult = $this->judge($game->id, $x, $y, $game->next);

        if ($winResult)
        {
            $game->next = null;
            $game->winner = $game->next;
        }
        else
        {
            $game->current_num = $game->current_num + 1;
            if ($game->next == 'black')
            {
                $game->next = 'white';
            }
            else
            {
                $game->next = 'black';
            }
        }

        $game->save();
    }

    private function judge($gameId, $row, $column, $currentStep)
    {
        $steps = Step::where('game_id', $gameId)->orderBy("step_num", "asc")->get();
        $chessArray = [9][9];
        foreach ($steps as $step)
        {
            if ($step->step_num % 2 == 1)
            {
                $chessArray[$step->x][$step->y] = 'black';
            }
            else
            {
                $chessArray[$step->x][$step->y] = 'white';
            }
        }
        /* 水平方向 */
        $count = 1;
        for ($i = $row - 1; $i >= 0; $i--) {
            if ($chessArray[$i][$column] == $currentStep) {
                $count++;
            } else {
                break;
            }
        }
        for ($i = $row + 1; $i < 9; $i++) {
            if ($chessArray[$i][$column] == $currentStep) {
                $count++;
            } else {
                break;
            }
        }
        if ($count >= 5) {
            return true;
        }

        /* 竖直方向 */
        $count = 1;
        for ($i = $column - 1; $i >= 0; $i--) {
            if ($chessArray[$row][$i] == $currentStep) {
                $count++;
            } else {
                break;
            }
        }
        for ($i = $column + 1; $i < 9; $i++) {
            if ($chessArray[$row][$i] == $currentStep) {
                $count++;
            } else {
                break;
            }
        }
        if ($count >= 5) {
            return true;
        }

        /* 斜着方向 1 */
        $count = 1;
        for ($i = $row - 1, $j = $column -  1; $i >= 0 && $j>= 0; $i--, $j--) {
            if ($chessArray[$i][$j] == $currentStep) {
                $count++;
            } else {
                break;
            }
        }
        for ($i = $row + 1, $j = $column + 1; $i < 9 && $j < 9; $i++, $j++) {
            if ($chessArray[$i][$j] == $currentStep) {
                $count++;
            } else {
                break;
            }
        }
        if ($count >= 5) {
            return true;
        }

        /* 斜着方向 2 */
        $count = 1;
        for ($i = $row + 1, $j = $column -  1; $i < 9 && $j>= 0; $i++, $j--) {
            if ($chessArray[$i][$j] == $currentStep) {
                $count++;
            } else {
                break;
            }
        }
        for ($i = $row - 1, $j = $column + 1; $i >= 0 && $j < 9; $i--, $j++) {
            if ($chessArray[$i][$j] == $currentStep) {
                $count++;
            } else {
                break;
            }
        }
        if ($count >= 5) {
            return true;
        }

        return false;
    }
}
