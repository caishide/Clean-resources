<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Captcha extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */

    public $path;
    public $custom;

    public function __construct($path = null,$custom = false)
    {
        $this->path = $path;
        $this->custom = $custom;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        if ($this->path) {
            return view($this->path.'.captcha');
        }
        $custom = $this->custom;
        return view('partials.captcha',compact('custom'));
    }
}
