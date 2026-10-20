<?php

namespace App\Service;

/**
 * Source of client testimonials (sample data until a database exists).
 */
final class TestimonialProvider
{
    /**
     * @return list<array{author: string, year: int, quote: string, initial: string}>
     */
    public function findAll(): array
    {
        $rows = [
            ['author' => 'Hannah Montana', 'year' => 2026, 'quote' => 'Thank you kaayo sa inyo arrangement <3 gwapa kaayo ag bulak and happy kaayo ang ako miga intawon pag dawat niya.'],
            ['author' => 'Donny Pangilinan', 'year' => 2024, 'quote' => "OMG, Thankk youuu kaayoo for the Prettyy flowerss!! 😭🩶 I didn't expect it to be this big and very fressh sa personal. I'm amazed by how it was delivered securely and on time huhu. Thank you so much for making my day soo wonderful as this flowers. We will surely be ordering again! 🫶💗"],
            ['author' => 'Annabelle', 'year' => 2025, 'quote' => 'Huge thank you for the stunning arrangement you put together for my anniversary! My partner was absolutely blown away by how huge and vibrant the bouquet was, and it honestly made our whole day so much more special. The delivery was right on time, the packaging was super cute, and the blooms are still looking perfect days later. You guys are the absolute best!'],
            ['author' => 'Sarah De Guzman', 'year' => 2026, 'quote' => "Absolutely stunning work. The flowers were incredibly fresh, and the composition was effortless and elegant. It's rare to see such thoughtful attention to detail in floral design. I couldn't be happier!"],
        ];

        return array_map(
            static fn (array $row) => $row + ['initial' => mb_strtoupper(mb_substr($row['author'], 0, 1))],
            $rows
        );
    }
}
