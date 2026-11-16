<?php
/**
 * doctors.php — Specialty mapping and provider data
 *
 * HOW IT WORKS:
 * Mayo Clinic article responses include a meta.controlWords.category tree.
 * Each node in that tree has an mcvId — a Mayo Controlled Vocabulary ID.
 * $specialtyMap maps those IDs to a specialty name.
 * $doctorsBySpecialty maps specialty names to placeholder provider data.
 *
 * TO ADD A NEW SPECIALTY:
 * 1. Find the mcvId in the article's JSON response (meta.controlWords.category)
 * 2. Add it to $specialtyMap with the specialty name
 * 3. Add provider entries to $doctorsBySpecialty under that specialty name
 *
 * mcvId reference — INVENTED example codes (look up real ones with the
 * plugin's category inspector on your own licensed content):
 *   7001  Diagnosed conditions (generic)
 *   7300  Pregnancy
 *   7103  Cardiovascular / Digestive system (context-dependent)
 *   1255  Quit smoking
 *   7105  Intestines
 *   7002  Pain
 *   7301  Pregnancy timeline
 *   7200  Healthy living
 *   7201  Fitness
 *   7202  Food and nutrition
 *   7204  Cardio exercise
 *   7003  Cancer
 *   7004  Breast cancer
 *   7006  Gas and bloating
 *   7203 Exercise
 *   7106 Digestive tract
 */

// Maps Mayo mcvId -> specialty name
$specialtyMap = [
    '7001'   => 'Internal Medicine', // Diagnosed conditions (generic)
    '7300'   => 'OB/GYN',            // Pregnancy
    '7103'   => 'Cardiology',        // Cardiovascular system
    '1255'   => 'Pulmonology',       // Quit smoking / Tobacco use
    '7105'   => 'Gastroenterology',  // Intestines
    '7002'   => 'Pain Management',   // Pain
    '7301'   => 'OB/GYN',            // Pregnancy timeline
    '7200'   => 'Wellness',          // Healthy living
    '7201'   => 'Sports Medicine',   // Fitness
    '7202'   => 'Nutrition',         // Food and nutrition
    '7204'   => 'Sports Medicine',   // Cardio exercise
    '7003'   => 'Oncology',          // Cancer
    '7004'   => 'Oncology',          // Breast cancer
    '7006'   => 'Gastroenterology',  // Gas and bloating
    '7203' => 'Sports Medicine',   // Exercise
    '7106' => 'Gastroenterology',  // Digestive tract
];

// Maps specialty name -> array of provider placeholder data
// Replace name, title, and phone with real provider data when available.
$doctorsBySpecialty = [
    'Cardiology' => [
        ['name' => 'Dr. Sarah Mitchell', 'title' => 'Cardiologist',        'phone' => '(555) 201-1000'],
        ['name' => 'Dr. James Harlow',   'title' => 'Cardiologist',        'phone' => '(555) 201-1001'],
    ],
    'Gastroenterology' => [
        ['name' => 'Dr. Linda Park',     'title' => 'Gastroenterologist',  'phone' => '(555) 201-2000'],
        ['name' => 'Dr. Tom Reyes',      'title' => 'Gastroenterologist',  'phone' => '(555) 201-2001'],
    ],
    'Oncology' => [
        ['name' => 'Dr. Rachel Nguyen',  'title' => 'Oncologist',          'phone' => '(555) 201-3000'],
        ['name' => 'Dr. Brian Foster',   'title' => 'Oncologist',          'phone' => '(555) 201-3001'],
    ],
    'OB/GYN' => [
        ['name' => "Dr. Maria O'Brien",  'title' => 'OB/GYN',              'phone' => '(555) 201-4000'],
        ['name' => 'Dr. Susan Caldwell', 'title' => 'OB/GYN',              'phone' => '(555) 201-4001'],
    ],
    'Pain Management' => [
        ['name' => 'Dr. Kevin Walsh',    'title' => 'Pain Specialist',     'phone' => '(555) 201-5000'],
    ],
    'Pulmonology' => [
        ['name' => 'Dr. Alan Torres',    'title' => 'Pulmonologist',       'phone' => '(555) 201-6000'],
    ],
    'Sports Medicine' => [
        ['name' => 'Dr. Chris Daniels',  'title' => 'Sports Medicine',     'phone' => '(555) 201-7000'],
    ],
    'Nutrition' => [
        ['name' => 'Dr. Amy Chen',       'title' => 'Dietitian',           'phone' => '(555) 201-8000'],
    ],
    'Internal Medicine' => [
        ['name' => 'Dr. Robert Ellis',   'title' => 'Internal Medicine',   'phone' => '(555) 201-9000'],
        ['name' => 'Dr. Paula Greene',   'title' => 'Internal Medicine',   'phone' => '(555) 201-9001'],
    ],
    'Wellness' => [
        ['name' => 'Dr. Diane Brooks',   'title' => 'Wellness Physician',  'phone' => '(555) 201-0000'],
    ],
];
