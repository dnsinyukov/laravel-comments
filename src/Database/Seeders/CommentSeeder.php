<?php

// src/Database/Seeders/CommentSeeder.php

namespace Coderden\Comments\Database\Seeders;

use Illuminate\Database\Seeder;
use Coderden\Comments\Models\Comment;
use Coderden\Comments\Models\CommentLike;
use Coderden\Comments\Models\CommentAbuseReport;
use Coderden\Comments\Models\CommentAttachment;

class CommentSeeder extends Seeder
{
    public function run()
    {
        // Проверяем наличие пользователей
        if (!\App\Models\User::exists()) {
            $this->command->warn('No users found. Please seed users first.');
            return;
        }
        
        $users = \App\Models\User::limit(10)->get();
        
        // Создаем комментарии для разных типов сущностей
        $commentableTypes = ['App\Models\Manga', 'App\Models\Chapter', 'App\Models\Page'];
        
        foreach ($commentableTypes as $type) {
            for ($i = 1; $i <= 5; $i++) {
                $comment = Comment::create([
                    'commentable_type' => $type,
                    'commentable_id' => $i,
                    'user_id' => $users->random()->id,
                    'content' => $this->generateCommentContent(),
                    'status' => 'published',
                    'rating' => rand(-10, 100),
                    'likes_count' => rand(0, 50),
                    'dislikes_count' => rand(0, 20),
                    'replies_count' => rand(0, 10),
                ]);
                
                // Создаем ответы
                for ($j = 1; $j <= 3; $j++) {
                    $reply = Comment::create([
                        'commentable_type' => $type,
                        'commentable_id' => $i,
                        'user_id' => $users->random()->id,
                        'parent_id' => $comment->id,
                        'content' => $this->generateCommentContent(),
                        'status' => 'published',
                    ]);
                }
                
                // Добавляем лайки
                foreach ($users->random(3) as $user) {
                    CommentLike::create([
                        'comment_id' => $comment->id,
                        'user_id' => $user->id,
                        'type' => rand(0, 1) ? 'like' : 'dislike',
                    ]);
                }
                
                // Добавляем жалобы
                if (rand(0, 1)) {
                    CommentAbuseReport::create([
                        'comment_id' => $comment->id,
                        'user_id' => $users->random()->id,
                        'reason' => $this->getRandomReason(),
                        'status' => 'pending',
                    ]);
                }
            }
        }
        
        $this->command->info('Comments seeded successfully!');
    }
    
    private function generateCommentContent(): string
    {
        $comments = [
            'Great content! Really enjoyed reading this.',
            'Interesting perspective, thanks for sharing.',
            'Could you elaborate more on this point?',
            'This helped me a lot, thank you!',
            'I have a different opinion on this matter.',
            'Well written and informative.',
            'Looking forward to more content like this.',
            'This needs more clarification in my opinion.',
            'Excellent work, keep it up!',
            'I learned something new today, thanks!',
        ];
        
        return $comments[array_rand($comments)];
    }
    
    private function getRandomReason(): string
    {
        $reasons = ['spam', 'abuse', 'hate_speech', 'adult_content', 'spoiler', 'false_info', 'other'];
        return $reasons[array_rand($reasons)];
    }
}