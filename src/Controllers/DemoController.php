<?php

namespace Nexus\Controllers;

use Nexus\Core\View;

class DemoController
{

    public function home()
    {
        View::render('civicone/demo/home', [
            'pageTitle' => 'Modernising Community Engagement',
            'hTitle' => 'Modernising Community Engagement',
            'hSubtitle' => 'Empowering Irish Communities Through Digital Infrastructure'
        ]);
    }

    public function compliance()
    {
        View::render('civicone/demo/compliance', [
            'pageTitle' => 'Compliance & Security',
            'hTitle' => 'Government-Grade Infrastructure',
            'hSubtitle' => 'Security That Meets National Standards'
        ]);
    }

    public function hseCaseStudy()
    {
        View::render('civicone/demo/hse_case_study', [
            'pageTitle' => 'HSE Integration',
            'hTitle' => 'HSE Wellness Integration',
            'hSubtitle' => 'Reducing Healthcare Pressure via Community Action'
        ]);
    }

    public function councilCaseStudy()
    {
        View::render('civicone/demo/council_case_study', [
            'pageTitle' => 'Council Management',
            'hTitle' => 'Council Multi-Hub Management',
            'hSubtitle' => 'One County, Ten Hubs, One Dashboard'
        ]);
    }

    public function technicalSpecs()
    {
        View::render('civicone/demo/technical_specs', [
            'pageTitle' => 'Technical Specifications',
            'hTitle' => 'Technical Proposal',
            'hSubtitle' => 'Platform Architecture & Security'
        ]);
    }
}
