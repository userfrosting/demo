<?php
namespace UserFrosting\Sprinkle\Demo\Model;

use UserFrosting\Sprinkle\Core\Model\UFModel;

class Member extends UFModel
{
    public $timestamps = true;

    /**
     * @var string The name of the table for the current model.
     */
    protected $table = "members";

    protected $fillable = [
        "user_id",
        "subscribed"
    ];

    /**
     * Directly joins the related user, so we can do things like sort, search, paginate, etc.
     */
    public function scopeJoinUser($query)
    {
        $query = $query->select('members.*');

        $query = $query->leftJoin('users', 'members.user_id', '=', 'users.id');

        return $query;
    }

    /**
     * Get the user associated with this member.
     */
    public function user()
    {
        /** @var UserFrosting\Sprinkle\Core\Util\ClassMapper $classMapper */
        $classMapper = static::$ci->classMapper;

        return $this->belongsTo($classMapper->getClassMapping('user'), 'user_id');
    }
}
