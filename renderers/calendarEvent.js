import moodleSvg from "./moodleSVG.js";

export default {
	props:{
		event: {
			type: Object,
			required:true,
		},
	},
	components:{
		moodleSvg,
	},
	template: `
		<div class="moodleEventContent " >
			<div id="moodleEventIcon">
				<moodle-svg></moodle-svg>
			</div>
			<span id="moodleEventTitle" class="flex-grow-1 text-center"><strong v-html="event.titel"></strong></span>
			<span id="moodleEventDelimiter"> - </span>
			<span id="moodleEventTopic" >{{event.topic}}</span>
		</div>`,
};