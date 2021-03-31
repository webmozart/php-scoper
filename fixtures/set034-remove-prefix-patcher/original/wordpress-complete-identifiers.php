<?php

class WordPressExample
{
    public function test($slug, $title)
    {
        Humbug\add_action('admin_init', function () {});
        $post = new Humbug\WP_Post(1);
    }
}
