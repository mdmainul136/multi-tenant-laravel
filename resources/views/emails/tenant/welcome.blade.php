@component('mail::message')
# Welcome to Your New Workspace!

Hi {{ $tenantName }},

We're excited to have you on board. Your multi-tenant workspace has been successfully provisioned and is ready for use.

**Your Workspace Details:**
- **URL:** [{{ $url }}]({{ $url }})
- **Admin Email:** {{ $adminEmail }}

You can now log in and start configuring your modules, managing your team, and growing your business.

@component('mail::button', ['url' => $url])
Go to My Dashboard
@endcomponent

If you have any questions, feel free to reply to this email.

Thanks,<br>
The {{ config('app.name') }} Team
@endcomponent
