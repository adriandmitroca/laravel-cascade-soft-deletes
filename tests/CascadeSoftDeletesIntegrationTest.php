<?php

use Illuminate\Database\Capsule\Manager;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CascadeSoftDeletesIntegrationTest extends PHPUnit_Framework_TestCase
{
    public static function setupBeforeClass()
    {
        $manager = new Manager();
        $manager->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        $manager->setEventDispatcher(new Dispatcher(new Container()));

        $manager->setAsGlobal();
        $manager->bootEloquent();

        $manager->schema()->create('posts', function ($table) {
            $table->increments('id');
            $table->string('title');
            $table->string('body');
            $table->timestamps();
            $table->softDeletes();
        });

        $manager->schema()->create('comments', function ($table) {
            $table->increments('id');
            $table->integer('post_id')->unsigned();
            $table->string('body');
            $table->timestamps();
        });
    }


    /** @test */
    public function it_cascades_deletes_when_deleting_a_parent_model()
    {
        $post = Tests\Entities\Post::create([
            'title' => 'How to cascade soft deletes in Laravel',
            'body'  => 'This is how you cascade soft deletes in Laravel',
        ]);

        $this->attachCommentsToPost($post);

        $this->assertCount(3, $post->comments);
        $post->delete();
        $this->assertCount(0, Tests\Entities\Comment::where('post_id', $post->id)->get());
    }

    /**
     * @test
     * @expectedException              \LogicException
     * @expectedExceptionMessageRegExp /.* does not implement Illuminate\\Database\\Eloquent\\SoftDeletes/
     */
    public function it_takes_excepion_to_models_that_do_not_implement_soft_deletes()
    {
        $post = Tests\Entities\NonSoftDeletingPost::create([
            'title' => 'Testing when you can use this trait',
            'body'  => 'Ensure that you can only use this trait if it uses SoftDeletes',
        ]);

        $this->attachCommentsToPost($post);

        $post->delete();
    }

    /**
     * @test
     * @expectedException              \LogicException
     * @expectedExceptionMessageRegExp /.* \[.*\] must exist and return an object of type Illuminate\\Database\\Eloquent\\Relations\\Relation/
     */
    public function it_takes_exception_to_models_trying_to_cascade_deletes_on_invalid_relationships()
    {
        $post = Tests\Entities\InvalidRelationshipPost::create([
            'title' => 'Testing invalid cascade relationships',
            'body'  => 'Ensure you can only use this trait if the model defines valid relationships',
        ]);

        $this->attachCommentsToPost($post);

        $post->delete();
    }

    /** @test */
    public function it_ensures_that_no_deletes_are_performed_if_there_are_invalid_relationships()
    {
        $post = Tests\Entities\InvalidRelationshipPost::create([
            'title' => 'Testing deletes are not executed',
            'body'  => 'If an invalid relationship is encountered, no deletes should be perofrmed',
        ]);

        $this->attachCommentsToPost($post);

        try {
            $post->delete();
        } catch (\LogicException $e) {
            $this->assertNotNull(Tests\Entities\InvalidRelationshipPost::find($post->id));
            $this->assertCount(3, Tests\Entities\Comment::where('post_id', $post->id)->get());
        }
    }

    /** @test */
    public function it_can_accept_cascade_deletes_as_a_single_string()
    {
        $post = Tests\Entities\PostWithStringCascade::create([
            'title' => 'Testing you can use a string for a single relationship',
            'body'  => 'This falls more closely in line with how other things work in Eloquent',
        ]);

        $this->attachCommentsToPost($post);

        $post->delete();

        $this->assertNull(Tests\Entities\Post::find($post->id));
        $this->assertCount(1, Tests\Entities\Post::withTrashed()->where('id', $post->id)->get());
        $this->assertCount(0, Tests\Entities\Comment::where('post_id', $post->id)->get());
    }

    /**
     * @test
     * @expectedException              \LogicException
     * @expectedExceptionMessageRegExp /Relationship \[.*\] must exist and return an object of type Illuminate\\Database\\Eloquent\\Relations\\Relation/
     */
    public function it_handles_situations_where_the_relationship_method_does_not_exist()
    {
        $post = Tests\Entities\PostWithMissingRelationshipMethod::create([
            'title' => 'Testing that missing relationship methods are accounted for',
            'body'  => 'In this way, you need not worry about Laravel returning fatal errors',
        ]);

        $post->delete();
    }

    /** @test */
    public function it_handles_soft_deletes_inherited_from_a_parent_model()
    {
        $post = Tests\Entities\ChildPost::create([
            'title' => 'Testing child model inheriting model trait',
            'body'  => 'This should allow a child class to inherit the soft deletes trait',
        ]);

        $this->attachCommentsToPost($post);

        $post->delete();

        $this->assertNull(Tests\Entities\ChildPost::find($post->id));
        $this->assertCount(1, Tests\Entities\ChildPost::withTrashed()->where('id', $post->id)->get());
        $this->assertCount(0, Tests\Entities\Comment::where('post_id', $post->id)->get());
    }

    /**
     * Attach some dummy comments to the given post.
     *
     * @return void
     */
    private function attachCommentsToPost($post)
    {
        $post->comments()->saveMany([
            new Tests\Entities\Comment(['body' => 'This is the first test comment']),
            new Tests\Entities\Comment(['body' => 'This is the second test comment']),
            new Tests\Entities\Comment(['body' => 'This is the third test comment']),
        ]);
    }

}
