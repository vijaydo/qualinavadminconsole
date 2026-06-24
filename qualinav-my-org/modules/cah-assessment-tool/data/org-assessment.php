<?php
return [
  'slug' => 'org-assessment',
  'title' => 'Organizational Assessment for Critical Access Hospitals (CAH)',
  'instructions' => '
    <p><strong>Instructions:</strong> Please rate your organization’s current status on the following elements using the 1–5 Likert Scale.</p>
    <p><strong>1 (Not in Place):</strong> No process or policy exists.<br>
       <strong>2 (Planning Phase):</strong> Discussions have started, but no formal action has been taken.<br>
       <strong>3 (Partial Implementation):</strong> Process is defined but inconsistently applied; some departments are missing.<br>
       <strong>4 (Substantial Compliance):</strong> Process is in place and effective, with only minor gaps in documentation.<br>
       <strong>5 (Fully Implemented/Optimized):</strong> Consistent, hospital-wide compliance with strong evidence available for surveyors.</p>
  ',
  'scale' => [1,2,3,4,5],
  'scale_labels' => [
    1 => 'Not in Place',
    2 => 'Planning',
    3 => 'Partial',
    4 => 'Substantial',
    5 => 'Optimized',
  ],
  'thresholds' => [
    'compliant_min' => 4.0,
    'partial_min' => 3.0,
  ],
  'sections' => [
    [
      'id' => 'governance_leadership',
      'title' => 'Section 1: Governance & Leadership Accountability',
      'focus' => 'Board engagement, active oversight, and resource allocation.',
      'questions' => [
        ['id' => 'board_engagement', 'label' => 'Board Engagement: The Board receives a quality report at every meeting, and minutes reflect active discussion/questioning rather than just receipt of report.'],
        ['id' => 'qapi_plan_approval', 'label' => 'QAPI Plan Approval: There is a written QAPI Plan that describes accountability and resource allocation, reviewed and approved by the Board annually.'],
        ['id' => 'unified_oversight', 'label' => 'Unified Oversight (System Integration): If part of a larger system, the CAH has a unified program that accounts for the unique circumstances and patient population of the rural site (per §485.641(f)).'],
        ['id' => 'leadership_presence', 'label' => 'Leadership Presence: Executive leadership (CEO/CNO) actively sits on the Quality Committee and participates in Root Cause Analyses (RCAs).'],
        ['id' => 'resource_allocation', 'label' => 'Resource Allocation: Documented evidence shows the Board approved specific budgets/resources for quality (e.g., software, FTE Quality Director).'],
      ],
    ],
    [
      'id' => 'program_design_scope',
      'title' => 'Section 2: Program Design & Scope (The “CAH-Wide” Mandate)',
      'focus' => 'Comprehensive inclusion of all departments, including contracted services.',
      'questions' => [
        ['id' => 'departmental_inclusion', 'label' => 'Departmental Inclusion: Every department, including non-clinical areas (Billing, Housekeeping, IT), tracks at least one active quality indicator.'],
        ['id' => 'contracted_services_oversight', 'label' => 'Contracted Services Oversight: Services provided under arrangement (outsourced Radiology, Lab, PT, Dietary) are integrated into QAPI oversight, with performance data reviewed quarterly.'],
        ['id' => 'priority_setting', 'label' => 'Priority Setting: PI projects are selected based on data showing they are High-Volume, High-Risk, or Problem-Prone.'],
        ['id' => 'adverse_event_system', 'label' => 'Adverse Event System: A clear, non-punitive system exists for identifying, reporting, and investigating medical errors and near misses across all departments.'],
      ],
    ],
    [
      'id' => 'data_benchmarking',
      'title' => 'Section 3: Data Collection & Clinical Benchmarking',
      'focus' => 'Data validity, external comparison, and MBQIP domains.',
      'questions' => [
        ['id' => 'mbqip_participation', 'label' => 'MBQIP Participation: The hospital actively reports on all four MBQIP domains (Patient Safety, Patient Engagement/HCAHPS, Care Coordination/EDTC, ED Throughput).'],
        ['id' => 'data_validation', 'label' => 'Data Validation: A validation process exists (e.g., second person re-abstracts at least 5% of charts monthly) to ensure accuracy.'],
        ['id' => 'peer_comparison', 'label' => 'Peer Comparison: The hospital benchmarks performance against external databases (e.g., CAHMPAS or Care Compare) to compare with rural peers.'],
        ['id' => 'trend_analysis', 'label' => 'Trend Analysis: Leadership reports show trends over at least 4 quarters against a national benchmark.'],
      ],
    ],
    [
      'id' => 'patient_safety_risk',
      'title' => 'Section 4: Patient Safety & Risk Management',
      'focus' => 'Proactive risk assessment, culture of safety, and harm reduction.',
      'questions' => [
        ['id' => 'safety_culture_survey', 'label' => 'Safety Culture Survey: The hospital conducts an annual Culture of Safety survey and debriefs results with staff.'],
        ['id' => 'proactive_risk_assessment', 'label' => 'Proactive Risk Assessment (FMEA): At least one FMEA is conducted annually on a high-risk process before harm occurs.'],
        ['id' => 'event_analysis_rca', 'label' => 'Event Analysis (RCA): Serious events are analyzed using a robust tool to implement system-level changes rather than only staff education.'],
        ['id' => 'good_catch_reporting', 'label' => 'Good Catch Reporting: There is evidence staff report near-misses without fear, demonstrating a Just Culture.'],
      ],
    ],
    [
      'id' => 'care_transitions_equity',
      'title' => 'Section 5: Care Transitions & Equity (2025 Updates)',
      'focus' => 'Safe transfers, discharge planning, and identifying disparities.',
      'questions' => [
        ['id' => 'edtc_compliance', 'label' => 'ED Transfer Communication (EDTC): The hospital maintains >90% compliance, ensuring key info is sent within 60 minutes of transfer.'],
        ['id' => 'equity_strategy', 'label' => 'Equity Strategy (TJC/CoP): QAPI identifies a priority population and collects Race, Ethnicity, and Language (REaL) data at registration.'],
        ['id' => 'community_alignment', 'label' => 'Community Alignment (SDOH): Quality initiatives align with CHNA to address local barriers (e.g., transportation).'],
        ['id' => 'discharge_planning', 'label' => 'Discharge Planning: Plans are periodically reviewed to ensure patient-centered and address social drivers of health for readmitted patients.'],
      ],
    ],
  ],
];
