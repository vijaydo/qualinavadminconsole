<?php
return [
  'slug' => 'tjc-readiness-2026',
  'title' => '2026 CAH TJC Survey Readiness Assessment',
  'instructions' => '
    <p>This readiness assessment uses a 5-point Likert scale to help identify areas of false confidence versus true survey readiness.</p>
    <p><strong>1:</strong> Non-Compliant (No process/docs exist)<br>
       <strong>2:</strong> Partial/Inconsistent (Process exists but staff unaware or documentation spotty)<br>
       <strong>3:</strong> Compliant but Untested (Policy solid; docs ready; no mock tracer)<br>
       <strong>4:</strong> Survey Ready (Process hardwired; staff trained; docs organized/current)<br>
       <strong>5:</strong> Sustained Excellence (100% compliance over 12+ months)</p>
  ',
  'scale' => [1,2,3,4,5],
  'scale_labels' => [
    1 => 'Non-Compliant',
    2 => 'Partial',
    3 => 'Untested',
    4 => 'Survey Ready',
    5 => 'Excellence',
  ],
  'thresholds' => [
    'compliant_min' => 4.0,
    'partial_min' => 3.0,
  ],
  'sections' => [
    [
      'id' => 'phase1_arrival',
      'title' => 'Phase 1: Immediate Arrival & Logistics',
      'focus' => 'Surveyors arrive unannounced, usually by 7:45 a.m.',
      'questions' => [
        ['id' => 'front_desk_preparedness', 'label' => 'Front Desk Preparedness: Reception verifies photo ID badges and validates the survey via the Joint Commission Connect extranet.'],
        ['id' => 'immediate_document_access', 'label' => 'Immediate Document Access: Inpatient lists, org charts, and floor plans are printed or accessible in under 15 minutes.'],
        ['id' => 'safety_briefing_readiness', 'label' => 'Safety Briefing Readiness: A staff member is designated and trained to give a <5-minute briefing on fire, active shooter, and local weather risks.'],
      ],
    ],
    [
      'id' => 'phase2_programmatic',
      'title' => 'Phase 2: Programmatic Compliance (Evaluation Modules)',
      'focus' => 'Deep-dive reviews into management of the hospital.',
      'questions' => [
        ['id' => 'qapi_integration', 'label' => 'QAPI Integration: QAPI involves every department and all contracted services.'],
        ['id' => 'em_hva_eop', 'label' => 'Emergency Management (EM): HVA and EOP updated and reviewed within the last 2 years.'],
        ['id' => 'medical_staff_credentialing', 'label' => 'Medical Staff Credentialing: Primary Source Verification for licensure/training/competence is present for all privileged practitioners.'],
        ['id' => 'oppe', 'label' => 'OPPE: Performance data is collected and reviewed for every practitioner at least every 12 months.'],
        ['id' => 'antibiotic_stewardship', 'label' => 'Antibiotic Stewardship: Multidisciplinary oversight exists and days-of-therapy/NHSN reports are available.'],
      ],
    ],
    [
      'id' => 'phase3_tracers',
      'title' => 'Phase 3: Clinical Environment & Tracers',
      'focus' => 'Evaluation of care at the bedside through observation and record review.',
      'questions' => [
        ['id' => 'two_patient_identifiers', 'label' => 'Two Patient Identifiers: Staff consistently use two unique identifiers (not room number) before meds/procedures.'],
        ['id' => 'medication_labeling', 'label' => 'Medication Labeling: All meds/containers/solutions are labeled immediately, including perioperative/procedural settings.'],
        ['id' => 'advance_directives', 'label' => 'Advance Directives: Documentation status is visible in a prominent part of the medical record for all inpatients.'],
        ['id' => 'suicide_risk_mitigation', 'label' => 'Suicide Risk Mitigation: Staff can articulate room-prep process in non-dedicated space for high-risk patients (remove ligatures/contraband).'],
      ],
    ],
    [
      'id' => 'phase4_life_safety',
      'title' => 'Phase 4: Physical Environment & Life Safety',
      'focus' => 'Life Safety Code and Health Care Facilities Code compliance.',
      'questions' => [
        ['id' => 'fire_drill_compliance', 'label' => 'Fire Drill Compliance: Records show drills conducted once per shift per quarter at varying times.'],
        ['id' => 'generator_testing', 'label' => 'Generator Testing: Weekly inspections and monthly 30-minute load tests (plus cool-down) are documented for emergency power systems.'],
        ['id' => 'medical_gas_safety', 'label' => 'Medical Gas Safety: Source/zone valves accessible; piping labeled every 20 feet and on both sides of penetrations.'],
      ],
    ],
  ],
];
