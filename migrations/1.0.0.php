<?php
    use Illuminate\Database\Capsule\Manager as Capsule;
    use Illuminate\Database\Schema\Blueprint;
    use UserFrosting\Sprinkle\Account\Model\User;
    use UserFrosting\Sprinkle\Demo\Model\Member;

    /**
     * Member table
     */
    if (!$schema->hasTable('members')) {
        $schema->create('members', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->unique();
            $table->boolean('subscribed')->default(true);
            $table->timestamps();

            $table->engine = 'InnoDB';
            $table->collation = 'utf8_unicode_ci';
            $table->charset = 'utf8';
            $table->foreign('user_id')->references('id')->on('users');
            $table->index('user_id');
        });

        $users = User::where('flag_verified', 1)->get();
        foreach ($users as $user) {
            $member = new Member([
                'user_id' => $user->id,
                'subscribed' => true
            ]);
            $member->save();
        }

        echo "Created table 'members'..." . PHP_EOL;
    } else {
        echo "Table 'members' already exists.  Skipping..." . PHP_EOL;
    }
    