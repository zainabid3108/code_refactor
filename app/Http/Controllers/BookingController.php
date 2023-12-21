<?php

namespace DTApi\Http\Controllers;

use Illuminate\Http\Request;
use DTApi\Models\Distance;
use DTApi\Models\Job;
use DTApi\Repository\BookingRepository;

class BookingTestController extends Controller
{
    protected $repository;

    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    private function jsonResponse($data, $statusCode = 200)
    {
        return response()->json($data, $statusCode);
    }

    public function index(Request $request)
    {
        $user = $request->__authenticatedUser;

        if ($user_id = $request->get('user_id')) {
            $response = $this->repository->getUsersJobs($user_id);
        } elseif ($user->user_type == config('app.admin_role_id') || $user->user_type == config('app.superadmin_role_id')) {
            $response = $this->repository->getAll($request);
        }

        return $this->jsonResponse($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);

        return $this->jsonResponse($job);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->store($request->__authenticatedUser, $data);

        return $this->jsonResponse($response);

    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, Request $request)
    {
        $data = $request->except(['_token', 'submit']);
        $cuser = $request->__authenticatedUser;
        $response = $this->repository->updateJob($id, $data, $cuser);
        return $this->jsonResponse($response);
    }

    /**
     * Handle immediate job email request.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function immediateJobEmail(Request $request)
    {
        $adminSenderEmail = config('app.adminemail');
        $data = $request->all();

        $response = $this->repository->storeJobEmail($data);

        return $this->jsonResponse($response);
    }

    /**
     * Get job history for a user.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|null
     */
    public function getHistory(Request $request)
    {
        $user_id = $request->get('user_id');

        if ($user_id) {
            $response = $this->repository->getUsersJobsHistory($user_id, $request);
            return $this->jsonResponse($response);
        }

        return null;
    }

    /**
     * Accept a job.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function acceptJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJob($data, $user);

        return $this->jsonResponse($response);
    }

    /**
     * Accept a job with a specified ID.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function acceptJobWithId(Request $request)
    {
        $jobId = $request->get('job_id');
        $user = $request->__authenticatedUser;

        $response = $this->repository->acceptJobWithId($jobId, $user);

        return $this->jsonResponse($response);
    }

    /**
     * Cancel a job.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function cancelJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->cancelJobAjax($data, $user);

        return $this->jsonResponse($response);
    }

    /**
     * End a job.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function endJob(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->endJob($data);

        return $this->jsonResponse($response);
    }

    /**
     * Handle customer not call request.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function customerNotCall(Request $request)
    {
        $data = $request->all();

        $response = $this->repository->customerNotCall($data);

        return $this->jsonResponse($response);
    }

    /**
     * Get potential jobs for a user.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getPotentialJobs(Request $request)
    {
        $user = $request->__authenticatedUser;

        $response = $this->repository->getPotentialJobs($user);

        return $this->jsonResponse($response);
    }

    /**
     * Update distance and job details based on input data.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function distanceFeed(Request $request)
    {
        $data = $request->all();

        $distance = $this->getValueOrDefault($data, 'distance', '');
        $time = $this->getValueOrDefault($data, 'time', '');
        $jobid = $this->getValueOrDefault($data, 'jobid', '');
        $session = $this->getValueOrDefault($data, 'session_time', '');

        $flagged = $this->getYesNoValue($data, 'flagged');
        $manually_handled = $this->getYesNoValue($data, 'manually_handled');
        $by_admin = $this->getYesNoValue($data, 'by_admin');
        $admincomment = $this->getValueOrDefault($data, 'admincomment', '');

        if ($time || $distance) {
            Distance::where('job_id', '=', $jobid)->update(['distance' => $distance, 'time' => $time]);
        }

        if ($admincomment || $session || $flagged || $manually_handled || $by_admin) {
            Job::where('id', '=', $jobid)->update([
                'admin_comments' => $admincomment,
                'flagged' => $flagged,
                'session_time' => $session,
                'manually_handled' => $manually_handled,
                'by_admin' => $by_admin,
            ]);
        }

        return $this->jsonResponse(['message' => 'Record updated!']);
    }

    /**
     * Reopen a job.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return $this->jsonResponse($response);
    }

    /**
     * Resend notifications for a job.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $jobId = $data['jobid'];

        $job = $this->repository->find($jobId);
        $jobData = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $jobData, '*');

        return $this->jsonResponse(['success' => 'Push sent']);
    }

    /**
     * Resend SMS notifications for a job.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->all();
        $jobId = $data['jobid'];

        $job = $this->repository->find($jobId);
        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return $this->jsonResponse(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return $this->jsonResponse(['error' => $e->getMessage()]);
        }
    }

    private function getValueOrDefault($data, $key, $default)
    {
        return isset($data[$key]) && $data[$key] !== "" ? $data[$key] : $default;
    }

    private function getYesNoValue($data, $key)
    {
        return isset($data[$key]) && $data[$key] === 'true' ? 'yes' : 'no';
    }

}
