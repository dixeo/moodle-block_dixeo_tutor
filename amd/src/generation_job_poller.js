define([
    'block_dixeo_tutor/constants',
], function(constants) {
    'use strict';

    /**
     * Poll a generation job until completed, failed, cancelled, or timeout.
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

        while (Date.now() < deadline) {
            if (isCancelled()) {
                return;
            }

            const status = await options.api.pollJobStatus(options.courseid, options.jobId);

            if (isCancelled()) {
                return;
            }

            if (status.status === 'completed') {
                await options.onCompleted(status);
                return;
            }
            if (status.status === 'failed' || status.status === 'cancelled') {
                throw new Error(status.status === 'cancelled' ? 'Cancelled' : 'Job failed');
            }

            await new Promise(function(resolve) {
                setTimeout(resolve, constants.polling.REPLY_INTERVAL_MS);
            });
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
