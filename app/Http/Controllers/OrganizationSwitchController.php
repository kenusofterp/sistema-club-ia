<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Cambia la entidad activa en el panel (personal) o en el portal (socio con varias membresías). */
class OrganizationSwitchController extends Controller
{
    public function admin(Request $request): RedirectResponse
    {
        $id = (int) $request->validate(['organization_id' => 'required|integer'])['organization_id'];
        abort_unless(in_array($id, $request->user()->adminOrganizationIds(), true), 403);

        $request->session()->put(ResolveOrganization::ADMIN_KEY, $id);

        return redirect()->route('admin.dashboard');
    }

    public function portal(Request $request): RedirectResponse
    {
        $id = (int) $request->validate(['organization_id' => 'required|integer'])['organization_id'];
        abort_unless(in_array($id, $request->user()->membershipOrganizationIds(), true), 403);

        $request->session()->put(ResolveOrganization::PORTAL_KEY, $id);

        return redirect()->route('portal.dashboard');
    }
}
