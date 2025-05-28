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
			<moodle-svg></moodle-svg>
			<span class="flex-grow-1 text-center"><strong v-html="event.titel"></strong> - {{event.topic}}</span>
		</div>`,
};