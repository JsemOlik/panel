@extends('layouts.admin')

@section('title')
    Administration
@endsection

@section('content-header')
    <h1>Administrative Overview<small>A quick glance at your system.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Index</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-fw fa-life-ring"></i> Potřebuješ pomoc?</h3>
            </div>
            <div class="box-body">
                <p>
                    Tohle je upravená verze Pterodactylu pro 4CAMPS. Pokud narazíš na jakýkoliv problém, napiš <strong>@jsemolik</strong> na Discordu,
                    nebo ho označ na Discord serveru <strong>4CAMPS - Tým</strong>. S problémy s vlastními funkcemi se prosím neobracej na oficiální
                    podporu Pterodactylu, ta s nimi pomoct nedokáže.
                </p>
                <div class="row">
                    <div class="col-sm-6">
                        <p class="no-margin-bottom"><strong>Při nahlášení problému uveď:</strong></p>
                        <ul class="no-margin-bottom">
                            <li>Co jsi dělal/a a co jsi čekal/a, že se stane.</li>
                            <li>Adresu stránky a server nebo uživatele, kterého se problém týká.</li>
                            <li>Přibližný čas, kdy se to stalo, aby šlo dohledat logy.</li>
                            <li>Snímek obrazovky s chybovou hláškou.</li>
                        </ul>
                    </div>
                    <div class="col-sm-6">
                        <p class="no-margin-bottom"><strong>Dobré vědět:</strong></p>
                        <ul class="no-margin-bottom">
                            <li>Nikdy na Discordu nesdílej hesla, API klíče ani OAuth client secrety.</li>
                            <li>Poskytovatele OAuth přihlášení spravuješ v sekci <a href="{{ route('admin.authentication') }}">Authentication</a>.</li>
                            <li>Zkratky konzole se nastavují pro každý egg v jeho záložce <strong>Shortcuts</strong>.</li>
                            <li>Pokud přestane fungovat přihlášení a přihlášení heslem je vypnuté, spusť na serveru <code>php artisan p:auth:password-login --enable</code></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-xs-12">
        <div class="box
            @if($version->isLatestPanel())
                box-success
            @else
                box-danger
            @endif
        ">
            <div class="box-header with-border">
                <h3 class="box-title">System Information</h3>
            </div>
            <div class="box-body">
                @if ($version->isLatestPanel())
                    You are running Pterodactyl Panel version <code>{{ config('app.version') }}</code>. Your panel is up-to-date!
                @else
                    Your panel is <strong>not up-to-date!</strong> The latest version is <a href="https://github.com/Pterodactyl/Panel/releases/v{{ $version->getPanel() }}" target="_blank"><code>{{ $version->getPanel() }}</code></a> and you are currently running version <code>{{ config('app.version') }}</code>. You can find instructions on how to update your panel <a href="https://pterodactyl.io/panel/1.0/updating.html">here</a>.
                @endif
            </div>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="{{ $version->getDiscord() }}"><button class="btn btn-warning" style="width:100%;"><i class="fa fa-fw fa-support"></i> Get Help <small>(via Discord)</small></button></a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="https://pterodactyl.io"><button class="btn btn-primary" style="width:100%;"><i class="fa fa-fw fa-link"></i> Documentation</button></a>
    </div>
    <div class="clearfix visible-xs-block">&nbsp;</div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="https://github.com/pterodactyl/panel"><button class="btn btn-primary" style="width:100%;"><i class="fa fa-fw fa-support"></i> GitHub</button></a>
    </div>
    <div class="col-xs-6 col-sm-3 text-center">
        <a href="{{ $version->getDonations() }}"><button class="btn btn-success" style="width:100%;"><i class="fa fa-fw fa-money"></i> Support the Project</button></a>
    </div>
</div>
@endsection
