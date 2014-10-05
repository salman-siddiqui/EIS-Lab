<?php require_once (ROOT . DS . 'application' . DS . 'views' . DS . 'activity_templates.php'); 
session_start(); ?>
<script type="text/javascript" src="static/js/profile.js"></script>
<script type="text/javascript" src="libraries/frontend/jquery-tmpl/jquery.tmpl.min.js"></script>

<script type="text/javascript">
    
getProfile(<?php echo $_GET['id']; ?>);
filterFollowers(<?php echo $_GET['id']; ?>,'');
getUserStream(<?php echo $_GET['id']; ?>,'','');

</script>

<!-- Check translation criteria and issue the corresponding badge -->

<?php
$sw_id = $_GET['id'];
$_SESSION['vid'] = $sw_id;
$con=mysqli_connect('localhost','root','','slidewiki');

// Check connection
if (mysqli_connect_errno()) {
	echo "Failed to connect to MySQL: " . mysqli_connect_error();
}
$sql = mysqli_query($con,"SELECT count(distinct(translated_from_revision)) FROM `slide_revision` where user_id= $sw_id and translated_from_revision<>'NULL';"); 
$sql1 = mysqli_query($con,"SELECT count(*) FROM `badge_import_details` where user_id=$sw_id ;"); 
$row = mysqli_fetch_array($sql);
$row1 = mysqli_fetch_array($sql1);


if (($row['count(distinct(translated_from_revision))'] ==1) && ($row1['count(*)']<>01)){
	echo "<h3>Your Basic Translation Badge is ready  claim it ";
	echo "<a href= http://salmansiddiqui.byethost15.com/badge-it-gadget-lite-master/process-badges/index.php?verified=1&badge=2 target=_blank height=430,width=800>here</a></h3>";
	mysqli_query($con,"INSERT INTO badge_import_details (badge_type,user_id) VALUES ('B', $sw_id)");//insert into table
}


if (($row['count(distinct(translated_from_revision))'] == 2) && ($row1['count(*)']<>02)){
	echo "<h3>Your Intermediate Translation Badge is ready  claim it ";
	echo "<a href= http://salmansiddiqui.byethost15.com/badge-it-gadget-lite-master/process-badges/index.php?verified=1&badge=3 target=_blank height=430,width=800>here</a></h3>";
	mysqli_query($con,"INSERT INTO badge_import_details (badge_type,user_id) VALUES ('I', $sw_id)");//insert into table
}

if (($row['count(distinct(translated_from_revision))'] ==3) && ($row1['count(*)']<>03)){
	echo "<h3>Your Advanced Translation Badge is ready  claim it ";
	echo "<a href= http://salmansiddiqui.byethost15.com/badge-it-gadget-lite-master/process-badges/index.php?verified=1&badge=4 target=_blank height=430,width=800>here</a></h3>";
	mysqli_query($con,"INSERT INTO badge_import_details (badge_type,user_id) VALUES ('A', $sw_id)");//insert into table
}



$con=mysqli_connect('localhost','root','','slidewiki');
// Check connection
if (mysqli_connect_errno()) {
	echo "Failed to connect to MySQL: " . mysqli_connect_error();
}
//$sql = mysqli_query($con,"SELECT count(*) FROM badge WHERE slidewiki_id=$sw_id;");
//$row = mysqli_fetch_array($sql);

//if ($row['count(*)'] ==0 ){
//mysqli_query($con,"INSERT INTO import_test (badge_type,user_id) VALUES ('A', '$sw_id')");

?>



<div id="msg_response" class="alert alert-block hide alert-success fade in" data-alert="alert">
    <a class="close pointer-cursor">Ã—</a>
    Your msg has been sent successfully.
</div>

<header class="page-header row">
    <div class="span14">
        <h1>
            <img src="./?url=ajax/getAvatarSrc&id=<?php echo trim($profile->id);?>" height="50" width="50" class="avatar" />
                <a href="user/<?php echo $_GET['id'];?>">
                <span class="r_entity r_profilepage" itemscope itemtype="http://schema.org/ProfilePage">
                	<meta itemprop="url" content="./user/<?php echo trim($profile->id);?>" />
	            	<span itemprop="accountablePerson" itemscope itemtype="http://schema.org/Person">
		            <span class="r_prop r_name" itemprop="name"><?php echo trim($profile->username); $_SESSION['uname']= $profile->username ?></span>
		            <meta itemprop="description" content="<?php echo trim($profile->description);?>" />
		            <meta itemprop="image" content="./?url=ajax/getAvatarSrc&id=<?php echo trim($profile->id);?>" />
		            <meta itemprop="url" content="<?php echo trim($profile->link);?>" />
		            <meta itemprop="familyName" content="<?php echo trim($profile->last_name);?>" />
		            <meta itemprop="gender" content="<?php echo trim($profile->gender);?>" />
		            <meta itemprop="givenName" content="<?php echo trim($profile->first_name);?>" />
		            </span>
          	  </span>    
            	</a>
            <?php if ($user['is_authorized'] && $_GET['id'] == $user['id']): ?>
                <a id="profile-edit-link" href="user/<?php echo $_GET['id'];?>/edit"><small>(Edit your profile)</small></h1></a>
            <?php endif; ?> 
            
            
                 
        
    </div>
    <div class="btn-toolbar" >
        <div class="btn-group" style="float:right;vertical-align:middle;clear:both;display:inline;">
            <?php if (($_GET['id'] != $user['id'])): ?>
                <?php if (($user['is_authorized'] && $_GET['id'] != $user['id']) && $isFollowing): ?>
                    <a class="btn small danger" onclick="follow($(this),'user',<?php echo $_GET['id'];?>)">Unfollow user</a>
                <?php endif; ?>

                <?php if (($user['is_authorized'] && $_GET['id'] != $user['id']) && !$isFollowing): ?>
                    <a  class="btn small success" onclick="follow($(this),'user',<?php echo $_GET['id'];?>)">Follow user</a>
                <?php endif; ?>

                <?php if (($user['is_authorized'] && $_GET['id'] != $user['id'])): ?>
                    <a  class="btn small " onclick="sendMsgDialog(<?php echo $_GET['id'];?>,'<?php echo $profile->username?>')"> <i class="icon-edit"></i> Write message</a>
                <?php endif; ?>	                
            <?php endif; ?>        
        </div>
    </div>
</header>


<article class="page-header profile_gradient">
    <section>
        <div class="form-stacked">
            <fieldset>
                <div id="full_profile" class="row"></div>                    
            </fieldset>
        </div>
    </section>
</article>

<article>
    <header>
        <nav>
            <ul class="tabs" data-tabs="tabs" id="item_tabs">                    
                <li class="active"><a href="#user_activities" id="user-activities_link">Latest activities</a></li>                   
                <li><a href="#followers" id="followers_link">Followers</a></li>
                <li><a href="./?url=user/showBadges"><? echo _("Earned Badges"); ?></a></li>
            </ul>
        </nav>
    </header>
    
<section class="tab-content">
            
    <div id="user_activities" class="active">
        <nav>
            
            <div class="btn-toolbar primary clearfix">
                <input class="span5" placeholder="Search..." id="keywords" onKeyUp="searchStream('my',<?php echo $_GET['id']; ?>)" value="">
                <div id="filter-array" style="float:right;vertical-align:bottom;clear:both;display:inline;" class="btn-group">
                    <a onclick="applyFilterUserStream($(this), <?php echo $_GET['id']; ?>)" id="filter0" filter="1" class="btn small success filter">Follow activities</a>
                    <a onclick="applyFilterUserStream($(this), <?php echo $_GET['id']; ?>)" id="filter1" filter="1" class="btn small success filter">Deck creation</a>
                    <a onclick="applyFilterUserStream($(this), <?php echo $_GET['id']; ?>)" id="filter2" filter="0" class="btn small success filter">Slide creation</a>
                    <a onclick="applyFilterUserStream($(this), <?php echo $_GET['id']; ?>)" id="filter3" filter="1" class="btn small success filter">Translation activities</a>
                    <a onclick="applyFilterUserStream($(this), <?php echo $_GET['id']; ?>)" id="filter4" filter="1" class="btn small success filter">Comments</a>
                    <a onclick="applyFilterUserStream($(this), <?php echo $_GET['id']; ?>)" id="filter5" filter="1" class="btn small success filter">Question creation</a>
                    <a onclick="applyFilterUserStream($(this), <?php echo $_GET['id']; ?>)" id="filter6" filter="1" class="btn small success filter">Test results</a>
                </div>
            </div>
        </nav>
        <article id="activity_stream"></article>        
    </div>
            
    <div id="followers">
        <header >
            <h3>Followers:</h3>
            <div class="span3">
                <div class="clearfix">
                    <div class="input">
                        <input type="text" name="filter_followers" placeholder="Search..." id="filter_followers" value="" onKeyUp = "filterFollowers(<?php echo $_GET['id'];?>,this.value)">
                    </div>
                </div>
            </div>
        </header>
        <div class="form-stacked">
            <fieldset>
                <div id="followers_profile"></div>
            </fieldset>
        </div>
        <footer></footer>
    </div>         
            
    </section>
</article>
 

<script id="full_profile_script" type="text/x-jquery-tmpl">
    <div class="span4"> 
        <div class="clearfix">
            <span class="avarat-text"> 
               <img width="150" class="deck-owner-avatar" title="${username}" src="./?url=ajax/getAvatarSrc&id=${id}">
            </span>                                
        </div>
    </div>
    <div class="span8">
        <div class="clearfix profile_description">
            
            <?php if ($user['is_authorized'] && $_GET['id'] == $user['id']):  //TODO: slug_title?>
            {{if description}}
                <div id="description_div" style="border: green dotted 1px;" title="click to edit" onclick="editDescription(<?php echo $user['id']; ?>)">
                    
                </div>
                {{if infodeck }}
                <div id="infodeck" class="profile_infodeck">
                    More info about ${username} you can find in the 
                   
                    <a id="link_to_presentation_url" deck_id="${infodeck}" href="./deck/${infodeck}">slides</a>
                </div>
                {{/if}}
            {{else}}
                <div id="insertDescriptionButton" style="min-height:50px;">                    
                    <button class="btn primary small" style="cursor:pointer" onclick="insertDescription(<?php echo $user['id']; ?>)">Add description</button>                   
                </div>
            {{if infodeck }}
                <div id="infodeck" class="profile_infodeck">
                    More info about ${username} you can find in the 
                    <a id="link_to_presentation_url" deck_id="${infodeck}" href="./deck/${infodeck}">slides</a>
                </div>
                {{/if}}
            {{/if}}
            <?php else : ?>
            {{if description}}
                <div id="description_div" style="cursor:default !important;"></div>
            {{else}}
            <div>${username} did not provide any additional information</div>
            {{/if}}
            {{if infodeck}}
                <div id="infodeck" class="profile_infodeck">More info about ${username} you can find in the <a id="link_to_presentation_url" href="./deck/${infodeck}">slides</a></div>
            {{/if}}                
            <?php endif; ?>
          
        </div>
    </div>
</script>

<script id="followers_profile_script" type="text/x-jquery-tmpl">
    <div class="span1_5"> 
        <div class="clearfix">                             
            <span class="avarat-text">
                <a href="user/${id}">
                    <img width="60" height="60" class="deck-owner-avatar" title="${username}" src="./?url=ajax/getAvatarSrc&id=${id}">
                </a>
            </span>                                
        </div>
    </div>
    <div class="span4">
        <div class="clearfix">
            <h4>
            {{if first_name}} 
                ${first_name} 
                {{if last_name}} 
                    ${last_name} 
                {{/if}}
            {{else}}
                ${username}
            {{/if}}
            </h4>                        
        </div>
    </div>
</script>
        
<div id="modal_msg" class="modal hide fade in" style="display: none;">
	<div class="modal-header">
		<a class="close pointer-cursor">Ã—</a>
			<h3>Message</h3>
	</div>
	<div class="modal-body">
		<div class="clearfix">
			<label for="receiver_id">Receiver &nbsp;</label>
			
			<div class="input">
				<select id="receiver_id"  class="span5">
						<option value="1" selected>soeren</option>
				</select>
			</div>
		</div>		
		<div class="clearfix">
			<label for="msg_title">Title &nbsp;</label>
			
			<div class="input">
				<input type="text" id="msg_title"  value="" class="span6" />
			</div>
		</div>	
		<div class="clearfix">
		<label for="msg_body">Content &nbsp;</label>
			<div class="input">
				<textarea id="msg_body" class="span6"></textarea>
			</div>
		</div>		
	</div>
	<div class="modal-footer">
		<a class="btn primary" onclick="send_msg();">Send</a>
	</div>
</div>
        

        
