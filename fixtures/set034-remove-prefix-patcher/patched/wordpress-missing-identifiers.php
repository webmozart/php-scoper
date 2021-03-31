<?php

class WordPressExample
{
    public function test($slug, $title)
    {
        add_action('admin_init', function () {});
        $post = new WP_Post(1);

        $option = Humbug\get_option('test_option');
    }
}
