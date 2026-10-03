<?php
use App\Models\User;
use App\Models\JobOffer;
use Illuminate\Support\Facades\DB;

$kernel = app(Illuminate\Contracts\Http\Kernel::class);
$shareOffer = JobOffer::query()->published()->where('is_job_share', true)->value('id');
$hrOffer = User::where('email','hr@zielonebiuro.test')->first()->company->jobOffers()->published()->value('id');
$pages = [
  ['marta@momjobs.test', '/candidate'],
  ['marta@momjobs.test', '/candidate/offers'],
  [null, '/offers'],
  ['hr@zielonebiuro.test', '/employer/offers'],
  ['hr@zielonebiuro.test', '/employer/candidates'],
  ['marta@momjobs.test', '/job-sharing'],
  ['marta@momjobs.test', $shareOffer ? "/job-sharing/offers/$shareOffer/partners" : null],
  ['hr@zielonebiuro.test', "/employer/offers/$hrOffer/job-share-pairs"],
];
$count = 0;
DB::listen(function () use (&$count) { $count++; });
foreach ($pages as [$email, $uri]) {
  if (!$uri) continue;
  auth()->forgetGuards();
  app('session')->flush();
  $request = Illuminate\Http\Request::create($uri, 'GET');
  $request->headers->set('X-Inertia', 'true');
  if ($email) { $u = User::where('email',$email)->first(); auth()->login($u); }
  $count = 0;
  $response = $kernel->handle($request);
  echo str_pad($uri, 45)." status ".$response->getStatusCode()." queries $count\n";
  $kernel->terminate($request, $response);
}
