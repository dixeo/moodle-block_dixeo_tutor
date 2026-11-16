define([
    'local_dixeo/network',
    'block_dixeo_tutor/chat_api',
    'block_dixeo_tutor/constants',
], function(Network, ChatAPI, constants) {
    'use strict';

    /**
     * Poll a generation job until completed, failed, cancelled, or timeout.
     *
     * One status read runs at a time. Connection failures are retried at the normal interval
     * within the retry window; past it, the last connection error is thrown so the flow shows
     * its network message.
     *
     * @param {Object} options
     * @param {Object} options.api API with pollJobStatus(courseid, jobId)
     * @param {number} options.courseid
     * @param {string} options.jobId
     * @param {number} options.generationToken
     * @param {function(): number} options.getGenerationToken
     * @param {function(Object): Promise<void>} options.onCompleted Called with status when job completes
     * @return {Promise<void>}
     */
    const pollGenerationJob = async function(options) {
        const deadline = Date.now() + constants.polling.TIMEOUT_MS;
        const isCancelled = function() {
            return options.generationToken !== options.getGenerationToken();
        };
        const retryWindow = Network.createRetryWindow();
        const wait = function() {
            return new Promise(function(resolve) {
                setTimeout(resolve, constants.polling.REPLY_INTERVAL_MS);
            });
        };

        while (Date.now() < deadline) {
            if (isCancelled()) {
                return;
            }

            let status;
            try {
                status = await options.api.pollJobStatus(options.courseid, options.jobId);
            } catch (failure) {
                if (isCancelled()) {
                    return;
                }
                if (ChatAPI.isConnectionFailure(failure) && retryWindow.shouldRetry(Network.connectionError(failure))) {
                    await wait();
                    continue;
                }
                throw failure;
            }

            if (isCancelled()) {
                return;
            }
            retryWindow.succeeded();

            if (status.status === 'completed') {
                await options.onCompleted(status);
                return;
            }
            if (status.status === 'failed' || status.status === 'cancelled') {
                throw new Error(status.status === 'cancelled' ? 'Cancelled' : 'Job failed');
            }

            await wait();
        }

        if (isCancelled()) {
            return;
        }
        throw new Error('Timeout');
    };

    return {
        pollGenerationJob: pollGenerationJob,
    };
});
