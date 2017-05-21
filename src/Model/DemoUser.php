<?php
namespace UserFrosting\Sprinkle\Demo\Model;

use Illuminate\Database\Capsule\Manager as Capsule;
use UserFrosting\Sprinkle\Account\Model\User;
use UserFrosting\Sprinkle\Demo\Model\Member;

trait LinkMember
{
    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function bootLinkMember()
    {
        /**
         * Create a new Member if necessary, and save the associated member every time.
         */
        static::saved(function ($demoUser)
        {
            $demoUser->createRelatedMemberIfNotExists();

            // When creating a new DemoUser, it might not have had a `user_id` when the `owler`
            // relationship was created.  So, we should set it on the Member if it hasn't been set yet.
            if (!$demoUser->member->user_id) {
                $demoUser->member->user_id = $demoUser->id;
            }

            $demoUser->member->save();
        });
    }
}
    
class DemoUser extends User
{
    use LinkMember;

    protected $fillable = [
        "user_name",
        "first_name",
        "last_name",
        "email",
        "locale",
        "theme",
        "group_id",
        "flag_verified",
        "flag_enabled",
        "last_activity_id",
        "password",
        "deleted_at",
        "subscribed"
    ];

    /**
     * Required to be able to access the `member` relationship in Twig without needing to do eager loading.
     * @see http://stackoverflow.com/questions/29514081/cannot-access-eloquent-attributes-on-twig/35908957#35908957
     */
    public function __isset($name)
    {
        if (in_array($name, [
            'member'
        ])) {
            return isset($this->member);
        } else {
            return parent::__isset($name);
        }
    }

    /**
     * Custom accessor for Member property
     */
    public function getSubscribedAttribute($value)
    {
        return (count($this->member) ? $this->member->subscribed : '');
    }

    /**
     * Get the member associated with this user.
     */
    public function member()
    {
        return $this->hasOne('\UserFrosting\Sprinkle\Demo\Model\Member', 'user_id');
    }

    /**
     * Custom mutator for Member property
     */
    public function setSubscribedAttribute($value)
    {
        $this->createRelatedMemberIfNotExists();

        $this->member->subscribed = $value;
    }

    /**
     * If this instance doesn't already have a related Member (either in the db on in the current object), then create one
     */
    protected function createRelatedMemberIfNotExists()
    {
        if (!count($this->member)) {
            $member = new Member([
                'user_id' => $this->id
            ]);

            $this->setRelation('member', $member);
        }
    }
}
