import axios from 'axios';
import { isDiscordActivity } from '../discord.js';

/**
 * Count that a part of the interface was opened (see ClickCountController
 * for the list). Anonymous, and never in the way: a failure is ignored.
 */
export function countClick(element) {
    axios.post('/usage/clicks', { element, context: isDiscordActivity ? 'activity' : 'website' })
        .catch(() => {});
}
