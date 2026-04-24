<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
alter publication supabase_realtime add table public.messages;
alter publication supabase_realtime add table public.leads;
alter publication supabase_realtime add table public.lead_activities;
SQL);

        DB::unprepared(<<<'SQL'
do $$
begin
    if not exists (
        select 1 from pg_policies
        where schemaname = 'public'
        and tablename = 'messages'
        and policyname = 'tenant_messages_select_policy'
    ) then
        create policy tenant_messages_select_policy
        on public.messages
        for select
        using (tenant_id = (auth.jwt() ->> 'tenant_id')::uuid);
    end if;
end $$;
SQL);

        DB::unprepared(<<<'SQL'
do $$
begin
    if not exists (
        select 1 from pg_policies
        where schemaname = 'public'
        and tablename = 'leads'
        and policyname = 'tenant_leads_select_policy'
    ) then
        create policy tenant_leads_select_policy
        on public.leads
        for select
        using (tenant_id = (auth.jwt() ->> 'tenant_id')::uuid);
    end if;
end $$;
SQL);

        DB::unprepared(<<<'SQL'
do $$
begin
    if not exists (
        select 1 from pg_policies
        where schemaname = 'public'
        and tablename = 'lead_activities'
        and policyname = 'tenant_lead_activities_select_policy'
    ) then
        create policy tenant_lead_activities_select_policy
        on public.lead_activities
        for select
        using (tenant_id = (auth.jwt() ->> 'tenant_id')::uuid);
    end if;
end $$;
SQL);

        DB::unprepared(<<<'SQL'
create index if not exists idx_messages_tenant_lead_created
on public.messages (tenant_id, lead_id, created_at desc);

create index if not exists idx_messages_tenant_created
on public.messages (tenant_id, created_at desc);

create index if not exists idx_leads_tenant_last_message
on public.leads (tenant_id, last_message_at desc);

create index if not exists idx_lead_activities_tenant_lead_created
on public.lead_activities (tenant_id, lead_id, created_at desc);
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
drop index if exists idx_lead_activities_tenant_lead_created;
drop index if exists idx_leads_tenant_last_message;
drop index if exists idx_messages_tenant_created;
drop index if exists idx_messages_tenant_lead_created;

drop policy if exists tenant_lead_activities_select_policy on public.lead_activities;
drop policy if exists tenant_leads_select_policy on public.leads;
drop policy if exists tenant_messages_select_policy on public.messages;
SQL);
    }
};
