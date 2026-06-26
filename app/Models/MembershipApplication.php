<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipApplication extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const MARITAL_MARRIED = 'married';
    public const MARITAL_SINGLE = 'single';

    protected $fillable = [
        'full_name',
        'mother_name',
        'birth_date',
        'registry_number',
        'registration_place',
        'phone',
        'emergency_phone',
        'email',
        'address',
        'profession',
        'marital_status',
        'children_count',
        'blood_type',
        'volunteer_areas',
        'volunteer_other',
        'has_volunteer_experience',
        'volunteer_experience_details',
        'id_document_path',
        'signature_name',
        'status',
        'admin_notes',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'children_count' => 'integer',
        'volunteer_areas' => 'array',
        'has_volunteer_experience' => 'boolean',
    ];
}
