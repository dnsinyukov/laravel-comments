<?php

namespace Coderden\Comments\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckCommentLimits
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        
        if (!$user) {
            return $next($request);
        }
        
        $dailyLimit = config('comments.limits.daily_comments', 50);
        $todayComments = $user->comments()
            ->whereDate('created_at', today())
            ->count();
        
        if ($todayComments >= $dailyLimit) {
            return response()->json([
                'error' => 'Daily comment limit reached',
                'limit' => $dailyLimit,
                'remaining' => 0,
            ], 429);
        }
        
        $request->merge([
            'comment_limit_remaining' => $dailyLimit - $todayComments,
        ]);
        
        return $next($request);
    }
}